<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Platform\DevProgram\ServiceInfoClient;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Platform\DevProgram\XmlResponse;
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
        $xml = file_get_contents(__DIR__ . '/../../Mocks/service-info-success.xml');
        $request = $this->getServiceInfoTransaction($xml)['request'];

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/rs/serviceinfo/shipment', $request->getUri()->getPath());
        self::assertSame('messageType=SO', $request->getUri()->getQuery());
        self::assertSame('application/vnd.cpc.serviceinfo-v2+xml', $request->getHeaderLine('Accept'));
        self::assertSame('en-CA', $request->getHeaderLine('Accept-Language'));
    }

    public function testSuccessfulResponse(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../Mocks/service-info-success.xml');
        $result = $this->getServiceInfoTransaction($xml)['result'];

        self::assertSame('Please note that we will be performing scheduled maintenance from Saturday, Oct. 12 at 9 p.m. to Sunday, Oct. 13 at 4 a.m. EDT. During this time, Canada Post web services will not be available.', $result[0]->messageText);
        self::assertSame('2013-10-12T21:00:00-05:00', $result[0]->fromDateTime->format(\DateTimeInterface::ATOM));
        self::assertSame('2013-10-13T04:00:00-05:00', $result[0]->toDateTime->format(\DateTimeInterface::ATOM));
    }

    public function testSuccessfulResponseWithNoMessages(): void
    {
        $result = $this->getServiceInfoTransaction('<info-messages></info-messages>')['result'];

        self::assertSame([], $result);
    }

    public function testSuccessfulResponseWithMultipleMessages(): void
    {
        $xml = <<<'EOF'
          <info-messages>
              <info-message>
                  <message-type>SO</message-type>
                  <message-text>Message 1</message-text>
                  <from-datetime>2013-10-12T21:00:00-05:00</from-datetime>
                  <to-datetime>2013-10-13T04:00:00-05:00</to-datetime>
              </info-message>
              <info-message>
                  <message-type>SO</message-type>
                  <message-text>Message 2</message-text>
                  <from-datetime>2014-10-12T21:00:00-05:00</from-datetime>
                  <to-datetime>2014-10-13T04:00:00-05:00</to-datetime>
              </info-message>
          </info-messages>
        EOF;
        $result = $this->getServiceInfoTransaction($xml)['result'];

        self::assertCount(2, $result);
        self::assertSame('Message 1', $result[0]->messageText);
        self::assertSame('Message 2', $result[1]->messageText);
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
        $xml = file_get_contents(__DIR__ . '/../../Mocks/service-info-success.xml');
        yield 'Empty message text' => [self::emptyField($xml, 'message-text'), 'empty info message'];
        yield 'Empty start date' => [self::emptyField($xml, 'from-datetime'), 'empty start date and time'];
        yield 'Empty end date' => [self::emptyField($xml, 'to-datetime'), 'empty end date and time'];
        yield 'Missing message text' => [self::removeField($xml, 'message-text'), 'empty info message'];
        yield 'Missing start date' => [self::removeField($xml, 'from-datetime'), 'empty start date and time'];
        yield 'Missing end date' => [self::removeField($xml, 'to-datetime'), 'empty end date and time'];
    }

    public function testFailedResponse(): void
    {
        $xml = <<<'EOF'
            <messages>
                <message>
                    <code>9182</code>
                    <description>The Message Type is invalid.</description>
                </message>
            </messages>
        EOF;
        try {
            $this->getServiceInfoTransaction($xml, 400)['result'];
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(400, $e->httpStatusCode);
            self::assertCount(1, $e->errors);
            self::assertTrue($e->hasErrorCode('9182'));
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

    private static function emptyField(string $xml, string $elementName): string
    {
        $doc = XmlResponse::load($xml);
        $doc->getElementsByTagName($elementName)->item(0)->textContent = '';
        return $doc->saveXML();
    }

    private static function removeField(string $xml, string $elementName): string
    {
        $doc = XmlResponse::load($xml);
        $doc->getElementsByTagName($elementName)->item(0)->remove();
        return $doc->saveXML();
    }
}
