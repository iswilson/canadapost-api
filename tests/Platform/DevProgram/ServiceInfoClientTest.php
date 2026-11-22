<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Platform\DevProgram\ServiceInfoClient;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use iswilson\CanadaPost\Tests\Support\XmlFixture;
use iswilson\CanadaPost\Tests\Traits\HttpMockHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Service Info Class Tests.
 */
#[CoversClass(ServiceInfoClient::class)]
#[Group('ServiceInfo')]
class ServiceInfoClientTest extends TestCase
{
    use HttpMockHandlerTrait;

    public function testRequest(): void
    {
        $xml = FixtureFile::load('DevProgram/get-service-info.xml');
        $request = $this->getServiceInfoTransaction($xml)['request'];

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/rs/serviceinfo/shipment', $request->getUri()->getPath());
        self::assertSame('messageType=SO', $request->getUri()->getQuery());
        self::assertSame('application/vnd.cpc.serviceinfo-v2+xml', $request->getHeaderLine('Accept'));
        self::assertSame('en-CA', $request->getHeaderLine('Accept-Language'));
    }

    public function testSuccessfulResponse(): void
    {
        $xml = FixtureFile::load('DevProgram/get-service-info.xml');
        $result = $this->getServiceInfoTransaction($xml)['result'];

        self::assertSame('Message 1.', $result[0]->messageText);
        self::assertSame('2026-01-01T21:00:00-05:00', $result[0]->fromDateTime->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-01-02T04:00:00-05:00', $result[0]->toDateTime->format(\DateTimeInterface::ATOM));
    }

    public function testSuccessfulResponseWithNoMessages(): void
    {
        $result = $this->getServiceInfoTransaction('<info-messages></info-messages>')['result'];

        self::assertSame([], $result);
    }

    public function testSuccessfulResponseWithMultipleMessages(): void
    {
        $result = $this->getServiceInfoTransaction(FixtureFile::load('DevProgram/get-service-info-multiple.xml'))['result'];

        self::assertCount(2, $result);
        self::assertSame('Message 1.', $result[0]->messageText);
        self::assertSame('Message 2.', $result[1]->messageText);
    }

    #[DataProvider('missingOrEmptyFields')]
    public function testMissingOrEmptyFields(string $xml, string $expectedExceptionMessage): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->getServiceInfoTransaction($xml)['result'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function missingOrEmptyFields(): iterable
    {
        $xml = FixtureFile::load('DevProgram/get-service-info.xml');
        yield 'Empty message text' => [XmlFixture::withEmptyElement($xml, 'message-text'), 'empty info message'];
        yield 'Empty start date' => [XmlFixture::withEmptyElement($xml, 'from-datetime'), 'empty start date and time'];
        yield 'Empty end date' => [XmlFixture::withEmptyElement($xml, 'to-datetime'), 'empty end date and time'];
        yield 'Missing message text' => [XmlFixture::withoutElement($xml, 'message-text'), 'empty info message'];
        yield 'Missing start date' => [XmlFixture::withoutElement($xml, 'from-datetime'), 'empty start date and time'];
        yield 'Missing end date' => [XmlFixture::withoutElement($xml, 'to-datetime'), 'empty end date and time'];
    }

    public function testFailedResponse(): void
    {
        try {
            $this->getServiceInfoTransaction(FixtureFile::load('DevProgram/error-multiple.xml'), 400)['result'];
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(400, $e->httpStatusCode);
            self::assertCount(2, $e->errors);
            self::assertTrue($e->hasErrorCode('TEST2'));
            self::assertTrue($e->hasErrorCode('TEST1'));
        }
    }

    private function serviceInfo(callable $handler): ServiceInfoClient
    {
        return new ServiceInfoClient(
            new Transport('https://localhost', 'username', 'password', ['handler' => $handler]),
        );
    }

    /**
     * @return array{
     *     request: \Psr\Http\Message\RequestInterface,
     *     response: \Psr\Http\Message\ResponseInterface|null,
     *     error: mixed,
     *     options: array<string, mixed>,
     *     result: list<\iswilson\CanadaPost\ServiceInfo\InfoMessage>,
     * }
     */
    private function getServiceInfoTransaction(string $xml, int $statusCode = 200): array
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response($statusCode, [], $xml),
        ]);

        // Add key 'result' for response after it passed through send.
        $response = $this->serviceInfo($handlerStack)->getServiceInfo();
        $historyContainer[0]['result'] = $response;

        return $historyContainer[0];
    }
}
