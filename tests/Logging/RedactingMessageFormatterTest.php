<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Logging;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\CanadaPost;
use iswilson\CanadaPost\Logging\RedactingMessageFormatter;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Tests\Support\Doubles\SpyLogger;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use iswilson\CanadaPost\Tests\Support\Traits\HttpMockHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(RedactingMessageFormatter::class)]
#[Group('Logging')]
final class RedactingMessageFormatterTest extends TestCase
{
    use HttpMockHandlerTrait;

    #[DataProvider('getSensitiveHttpRequestHeaders')]
    public function testRequestHeadersAreRedacted(string $redactedHeader): void
    {
        $request = new Request('POST', 'https://localhost', [
            $redactedHeader => 'testing123',
        ]);

        $messageFormatter = new RedactingMessageFormatter(new MessageFormatter(MessageFormatter::DEBUG));
        $message = $messageFormatter->format($request);

        self::assertStringContainsStringIgnoringCase(sprintf('%s: [REDACTED]', $redactedHeader), $message);
        self::assertStringNotContainsString('testing123', $message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getSensitiveHttpRequestHeaders(): iterable
    {
        yield 'Authorization' => ['Authorization'];
        yield 'Cookie' => ['Cookie'];
        yield 'Proxy-Authorization' => ['Proxy-Authorization'];
        yield 'Authorization in lowercase' => ['authorization'];
        yield 'Authorization in uppercase' => ['AUTHORIZATION'];
    }

    #[DataProvider('getSensitiveHttpResponseHeaders')]
    public function testResponseHeadersAreRedacted(string $redactedHeader): void
    {
        $request = new Request('POST', 'https://localhost');
        $response = new Response(200, [
            $redactedHeader => 'testing123',
        ]);

        $messageFormatter = new RedactingMessageFormatter(new MessageFormatter(MessageFormatter::DEBUG));
        $message = $messageFormatter->format($request, $response);

        self::assertStringContainsStringIgnoringCase(sprintf('%s: [REDACTED]', $redactedHeader), $message);
        self::assertStringNotContainsString('testing123', $message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getSensitiveHttpResponseHeaders(): iterable
    {
        yield 'Set-Cookie' => ['Set-Cookie'];
    }

    #[DataProvider('getSensitiveRatingFields')]
    public function testRatingRequestFieldsAreRedactedFromLog(string $fieldName, string $countryCode, string $postalCode): void
    {
        $spyLogger = new SpyLogger();
        $this->getSentRatingRequest($spyLogger, new Destination($countryCode, $postalCode));
        self::assertStringContainsString(sprintf('<%s>[REDACTED]</%s>', $fieldName, $fieldName), $spyLogger->records[0]['message']);
        self::assertStringNotContainsString($postalCode, $spyLogger->records[0]['message']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function getSensitiveRatingFields(): iterable
    {
        yield 'Postal code' => ['postal-code', 'CA', 'H0H0H0'];
        yield 'Zip code 5' => ['zip-code', 'US', '98765'];
        yield 'Zip code 5-4' => ['zip-code', 'US', '98765-4321'];
        yield 'International postal code' => ['postal-code', 'AQ', 'A1B2-C3D4'];
    }

    #[DataProvider('getSensitiveTrackingFields')]
    public function testTrackingResponseFieldsAreRedactedFromLog(string $fieldName, string $fieldValue): void
    {
        $spyLogger = new SpyLogger();
        $this->getSentTrackingRequest($spyLogger);
        self::assertStringContainsString(sprintf('<%s>[REDACTED]</%s>', $fieldName, $fieldName), $spyLogger->records[0]['message']);
        self::assertStringNotContainsString($fieldValue, $spyLogger->records[0]['message']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getSensitiveTrackingFields(): iterable
    {
        yield 'Destination postal ID' => ['destination-postal-id', 'K1A'];
        yield 'Customer reference #1' => ['customer-ref-1', 'testing123'];
        yield 'Customer reference #2' => ['customer-ref-2', 'testing456'];
        yield 'Signatory name' => ['signatory-name', 'testing789'];
    }

    public function testOtherRequestFieldsAreNotRedacted(): void
    {
        $spyLogger = new SpyLogger();
        $this->getSentRatingRequest($spyLogger, new Destination('AQ'));
        self::assertStringContainsString('<customer-number>12345789</customer-number>', $spyLogger->records[0]['message']);
        self::assertStringContainsString('<contract-id>135792468</contract-id>', $spyLogger->records[0]['message']);
        self::assertStringContainsString('<origin-postal-code>K1A0B1</origin-postal-code>', $spyLogger->records[0]['message']);
        self::assertStringContainsString('<country-code>AQ</country-code>', $spyLogger->records[0]['message']);
    }

    private function getSentRatingRequest(LoggerInterface $logger, ?Destination $destination = null): RequestInterface
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')),
        ]);

        CanadaPost::devProgram('username', 'password', '12345789', '135792468', logger: $logger, handler: $handlerStack)
            ->rating()
            ->getRates('K1A0B1', $destination ?? new Destination('CA', 'H0H0H0'), new Parcel(500));

        return $historyContainer[0]['request'];
    }

    private function getSentTrackingRequest(LoggerInterface $logger): RequestInterface
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-tracking-summary.xml')),
        ]);

        CanadaPost::devProgram('username', 'password', '12345789', '135792468', logger: $logger, handler: $handlerStack)
            ->tracking()
            ->getSummary('ABCDEF1234567890');

        return $historyContainer[0]['request'];
    }
}
