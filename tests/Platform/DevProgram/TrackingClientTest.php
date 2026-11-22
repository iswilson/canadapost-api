<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Platform\DevProgram\TrackingClient;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use iswilson\CanadaPost\Tests\Support\XmlFixture;
use iswilson\CanadaPost\Tests\Traits\HttpMockHandlerTrait;
use iswilson\CanadaPost\Tracking\DeliveryOptionCompleted;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(TrackingClient::class)]
#[Group('Tracking')]
class TrackingClientTest extends TestCase
{
    use HttpMockHandlerTrait;

    #[DataProvider('getValidPins')]
    public function testSummaryRequestWithValidPins(string $pin, string $expectedValue): void
    {
        $request = $this->getTrackingSummaryRequest($pin);
        self::assertSame(sprintf('/vis/track/pin/%s/summary', $expectedValue), $request->getUri()->getPath());
        self::assertSame('GET', $request->getMethod());
        self::assertSame('application/vnd.cpc.track-v2+xml', $request->getHeaderLine('Accept'));
        self::assertSame('en-CA', $request->getHeaderLine('Accept-Language'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getValidPins(): iterable
    {
        yield '12 characters' => ['AB1234567890', 'AB1234567890'];
        yield '13 characters' => ['ABC1234567890', 'ABC1234567890'];
        yield '16 characters' => ['ABCDEF1234567890', 'ABCDEF1234567890'];
        yield 'Lowercase' => ['abcdef1234567890', 'ABCDEF1234567890'];
        yield 'Has padding' => ['  ABCDEF1234567890   ', 'ABCDEF1234567890'];
    }

    #[DataProvider('getInvalidPins')]
    public function testSummaryRequestWithInvalidPins(string $pin): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PIN');
        $this->getTrackingSummaryRequest($pin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getInvalidPins(): iterable
    {
        yield '11 characters' => ['A1234567890'];
        yield '14 characters' => ['ABCD1234567890'];
        yield '15 characters' => ['ABCDE1234567890'];
        yield '17 characters' => ['ABCDEFG1234567890'];
        yield 'Empty' => [''];
        yield 'Symbols' => ['@B123$567890'];
        yield 'Separated with spaces' => ['AB12 3456 7890'];
        yield 'Path traversal' => ['../../../test'];
    }

    public function testFullSummary(): void
    {
        $xml = FixtureFile::load('DevProgram/get-tracking-summary.xml');

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], $xml),
        ]);

        $pinSummary = $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
        self::assertSame('ABCDEF1234567890', $pinSummary->pin);
        self::assertSame('H0H', $pinSummary->originPostalId);
        self::assertSame('K1A', $pinSummary->destinationPostalId);
        self::assertSame('ON', $pinSummary->destinationProvince);
        self::assertSame('Xpresspost', $pinSummary->serviceName);
        self::assertSame('2026-12-22', $pinSummary->mailedOnDate?->format('Y-m-d'));
        self::assertSame('2026-12-23', $pinSummary->expectedDeliveryDate?->format('Y-m-d'));
        self::assertSame('2026-12-25', $pinSummary->actualDeliveryDate?->format('Y-m-d'));
        self::assertSame(DeliveryOptionCompleted::Satisfied, $pinSummary->deliveryOptionCompletedInd);
        self::assertSame('20260625:070000', $pinSummary->eventDateTime?->format('Ymd:His'));
        self::assertSame('Item successfully delivered', $pinSummary->eventDescription);
        self::assertSame('2026-12-24', $pinSummary->attemptedDate?->format('Y-m-d'));
        self::assertSame('DELIVERED', $pinSummary->eventType);
        self::assertSame('OTTAWA,ON', $pinSummary->eventLocation);
    }

    public function testPartialDateTimeBecomesMidnight(): void
    {
        $xml = FixtureFile::load('DevProgram/get-tracking-summary.xml');
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], XmlFixture::withElementText($xml, 'mailed-on-date', '2026-01-01')),
        ]);

        $pinSummary = $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
        self::assertSame('2026-01-01 00:00:00', $pinSummary->mailedOnDate?->format('Y-m-d H:i:s'));
    }

    public function testEmptySummaryFields(): void
    {
        $xml = <<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <pin>ABCDEF1234567890</pin>
                    <origin-postal-id></origin-postal-id>
                    <destination-postal-id></destination-postal-id>
                    <destination-province></destination-province>
                    <service-name></service-name>
                    <mailed-on-date></mailed-on-date>
                    <expected-delivery-date></expected-delivery-date>
                    <actual-delivery-date></actual-delivery-date>
                    <delivery-option-completed-ind></delivery-option-completed-ind>
                    <event-date-time></event-date-time>
                    <event-description></event-description>
                    <attempted-date></attempted-date>
                    <event-type></event-type>
                    <event-location></event-location>
                </pin-summary>
            </tracking-summary>
        EOF;

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], $xml),
        ]);

        $pinSummary = $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
        self::assertSame('ABCDEF1234567890', $pinSummary->pin);
        self::assertNull($pinSummary->originPostalId);
        self::assertNull($pinSummary->destinationPostalId);
        self::assertNull($pinSummary->destinationProvince);
        self::assertNull($pinSummary->serviceName);
        self::assertNull($pinSummary->mailedOnDate);
        self::assertNull($pinSummary->expectedDeliveryDate);
        self::assertNull($pinSummary->actualDeliveryDate);
        self::assertNull($pinSummary->deliveryOptionCompletedInd);
        self::assertNull($pinSummary->eventDateTime);
        self::assertNull($pinSummary->eventDescription);
        self::assertNull($pinSummary->attemptedDate);
        self::assertNull($pinSummary->eventType);
        self::assertNull($pinSummary->eventLocation);
    }

    public function testEmptySummary(): void
    {
        $xml = <<<'EOF'
            <tracking-summary>
            </tracking-summary>
        EOF;

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], $xml),
        ]);

        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('empty tracking summary');
        $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
    }

    public function testMissingPin(): void
    {
        $xml = FixtureFile::load('DevProgram/get-tracking-summary.xml');

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], XmlFixture::withoutElement($xml, 'pin')),
        ]);

        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('empty PIN');
        $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
    }

    public function testEmptyPin(): void
    {
        $xml = FixtureFile::load('DevProgram/get-tracking-summary.xml');

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], XmlFixture::withEmptyElement($xml, 'pin')),
        ]);

        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('empty PIN');
        $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
    }

    public function testInvalidDeliveryOptionCompletedValue(): void
    {
        $xml = FixtureFile::load('DevProgram/get-tracking-summary.xml');

        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], XmlFixture::withElementText($xml, 'delivery-option-completed-ind', '3')),
        ]);

        $pinSummary = $this->tracking($handlerStack)->getSummary('ABCDEF1234567890');
        self::assertNull($pinSummary->deliveryOptionCompletedInd);
    }

    private function tracking(callable $handler): TrackingClient
    {
        return new TrackingClient(
            new Transport('https://localhost', 'username', 'password', ['handler' => $handler]),
        );
    }

    private function getTrackingSummaryRequest(string $pin): RequestInterface
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-tracking-summary.xml')),
        ]);

        $this->tracking($handlerStack)->getSummary($pin);

        return $historyContainer[0]['request'];
    }
}
