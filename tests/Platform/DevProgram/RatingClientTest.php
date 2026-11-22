<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Platform\DevProgram\RatingClient;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use iswilson\CanadaPost\Rating\ShipmentOption;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use iswilson\CanadaPost\Tests\Support\XmlFixture;
use iswilson\CanadaPost\Tests\Traits\HttpMockHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(RatingClient::class)]
#[Group('DevProgram')]
#[Group('Rating')]
final class RatingClientTest extends TestCase
{
    use HttpMockHandlerTrait;

    public function testRequest(): void
    {
        $xml = FixtureFile::load('DevProgram/get-rates.xml');
        $request = $this->getRatingTransaction($xml)['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/rs/ship/price', $request->getUri()->getPath());
        self::assertSame('application/vnd.cpc.ship.rate-v4+xml', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/vnd.cpc.ship.rate-v4+xml', $request->getHeaderLine('Accept'));
        self::assertSame('en-CA', $request->getHeaderLine('Accept-Language'));
    }

    public function testServiceCodesAreTrimmedAndCapitalized(): void
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')),
        ]);

        $this->rating($handlerStack)->getRates(
            'K1A0B1',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            serviceCodes: ['  dom.xp   '],
        );
        $request = $historyContainer[0]['request'];

        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame('DOM.XP', $body->getElementsByTagName('service-code')->item(0)?->textContent);
    }

    public function testServiceCodesAreDeduplicated(): void
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')),
        ]);

        $this->rating($handlerStack)->getRates(
            'K1A0B1',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            serviceCodes: [
                'dom.ep',
                'DOM.eP',
                'DOM.XP',
            ],
        );
        $request = $historyContainer[0]['request'];

        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertCount(2, $body->getElementsByTagName('service-code'));
        self::assertSame('DOM.EP', $body->getElementsByTagName('service-code')->item(0)?->textContent);
        self::assertSame('DOM.XP', $body->getElementsByTagName('service-code')->item(1)?->textContent);
    }

    public function testOptionCodesAreTrimmedAndCapitalized(): void
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')),
        ]);

        $this->rating($handlerStack)->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            shipmentOptions: [new ShipmentOption('  so   ')],
        );
        $request = $historyContainer[0]['request'];

        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame('SO', $body->getElementsByTagName('option-code')->item(0)?->textContent);
    }

    public function testOptionCodesIncludeAmount(): void
    {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')),
        ]);

        $this->rating($handlerStack)->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            shipmentOptions: [new ShipmentOption('COD', 100)],
        );
        $request = $historyContainer[0]['request'];

        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame('COD', $body->getElementsByTagName('option-code')->item(0)?->textContent);
        self::assertSame('1.00', $body->getElementsByTagName('option-amount')->item(0)?->textContent);
    }

    /**
     * Test successful rating response.
     */
    public function testValidRatingResponse(): void
    {
        $response = $this->getRatingResponse(FixtureFile::load('DevProgram/get-rates.xml'));
        self::assertEquals([
            new PriceQuote('DOM.EP', 'Expedited Parcel', 1130, hstCents: 130),
            new PriceQuote('DOM.XP', 'Xpresspost', 2260, hstCents: 260),
        ], $response);
    }

    public function testEmptyPriceQuotes(): void
    {
        $response = $this->getRatingResponse('<?xml version="1.0" encoding="UTF-8"?><price-quotes></price-quotes>');
        self::assertSame([], $response);
    }

    public function testResponseWithErrorMessages(): void
    {
        try {
            $this->getRatingResponse(FixtureFile::load('DevProgram/error-multiple.xml'));
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertCount(2, $e->errors);
            self::assertTrue($e->hasErrorCode('TEST2'));
            self::assertTrue($e->hasErrorCode('TEST1'));
            self::assertStringContainsString('TEST2, TEST1', $e->getMessage());
        }
    }

    #[DataProvider('getMissingOrEmptyFields')]
    public function testResponseWithMissingOrEmptyFields(string $xml, string $expectedExceptionMessage): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->getRatingResponse($xml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getMissingOrEmptyFields(): iterable
    {
        $xml = FixtureFile::load('DevProgram/get-rates.xml');
        yield 'Missing service code' => [XmlFixture::withoutElement($xml, 'service-code'), 'service code'];
        yield 'Empty service code' => [XmlFixture::withEmptyElement($xml, 'service-code'), 'service code'];
        yield 'Missing service name' => [XmlFixture::withoutElement($xml, 'service-name'), 'service name'];
        yield 'Empty service name' => [XmlFixture::withEmptyElement($xml, 'service-name'), 'service name'];
        yield 'Missing total due' => [XmlFixture::withoutElement($xml, 'due'), 'dollar amount'];
        yield 'Empty total due' => [XmlFixture::withEmptyElement($xml, 'due'), 'dollar amount'];
        yield 'Empty tax' => [XmlFixture::withEmptyElement($xml, 'pst'), 'dollar amount'];
    }

    public function testResponseMissingTax(): void
    {
        $xml = FixtureFile::load('DevProgram/get-rates.xml');
        $response = $this->getRatingResponse(XmlFixture::withoutElement($xml, 'hst'));
        self::assertSame(0, $response[0]->hstCents);
    }

    #[DataProvider('getDestinations')]
    public function testSendsToDestination(Destination $destination, string $expectedXml): void
    {
        $request = $this->getSentRequestBody($destination, new Parcel(500));
        $sentXml = $request->saveXML($request->getElementsByTagName('destination')->item(0));
        self::assertIsString($sentXml);
        self::assertXmlStringEqualsXmlString($expectedXml, $sentXml);
    }

    /**
     * @return iterable<string, array{Destination, string}>
     */
    public static function getDestinations(): iterable
    {
        yield 'Canada' => [
            new Destination('CA', 'H0H0H0'),
            '<destination><domestic><postal-code>H0H0H0</postal-code></domestic></destination>',
        ];
        yield 'US' => [
            new Destination('US', '12345'),
            '<destination><united-states><zip-code>12345</zip-code></united-states></destination>',
        ];
        yield 'International with postal code' => [
            new Destination('AQ', 'A1B2C3D4'),
            '<destination><international><country-code>AQ</country-code><postal-code>A1B2C3D4</postal-code></international></destination>',
        ];
        yield 'International without postal code' => [
            new Destination('AQ'),
            '<destination><international><country-code>AQ</country-code></international></destination>',
        ];
    }

    public function testOriginPostalCodeIsNormalized(): void
    {
        $request = $this->getSentRequestBody(new Destination('CA', 'H0H0H0'), new Parcel(500), ' h0h-0h0 ');
        $sentXml = $request->saveXML($request->getElementsByTagName('origin-postal-code')->item(0));
        self::assertIsString($sentXml);
        self::assertXmlStringEqualsXmlString('<origin-postal-code>H0H0H0</origin-postal-code>', $sentXml);
    }

    #[DataProvider('getValidFormatDecimalInputs')]
    public function testFormatDecimal(int $value, int $decimalPlaces, string $expectedValue): void
    {
        self::assertSame($expectedValue, RatingClient::formatDecimal($value, $decimalPlaces));
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function getValidFormatDecimalInputs(): iterable
    {
        yield 'Zero' => [0, 3, '0.000'];
        yield 'Needs padding' => [1, 3, '0.001'];
        yield 'Minimum decimal places' => [123, 1, '12.3'];
        yield 'Digits equal to decimal places' => [123, 3, '0.123'];
        yield 'More digits than decimal places' => [1234, 3, '1.234'];
    }

    #[DataProvider('getInvalidFormatDecimalInputs')]
    public function testInvalidFormatDecimal(int $value, int $decimalPlaces): void
    {
        $this->expectException(InvalidArgumentException::class);
        RatingClient::formatDecimal($value, $decimalPlaces);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function getInvalidFormatDecimalInputs(): iterable
    {
        yield 'Negative value' => [-1, 3];
        yield 'Negative decimal places' => [123, -1];
        yield 'Zero decimal places' => [123, 0];
    }

    #[DataProvider('getParcelCharacteristics')]
    public function testSendsParcelCharacteristics(int $weightGrams, ?int $lengthMm, ?int $widthMm, ?int $heightMm, string $expectedXml): void
    {
        $request = $this->getSentRequestBody(new Destination('CA', 'H0H0H0'), new Parcel($weightGrams, $lengthMm, $widthMm, $heightMm));
        $sentXml = $request->saveXML($request->getElementsByTagName('parcel-characteristics')->item(0));
        self::assertIsString($sentXml);
        self::assertXmlStringEqualsXmlString($expectedXml, $sentXml);
    }

    /**
     * @return iterable<string, array{int, ?int, ?int, ?int, string}>
     */
    public static function getParcelCharacteristics(): iterable
    {
        yield 'Weight only' => [
            500,
            null, null, null,
            '<parcel-characteristics><weight>0.500</weight></parcel-characteristics>',
        ];
        yield 'Include unsorted dimensions' => [
            500,
            654, 321, 9999,
            '<parcel-characteristics><weight>0.500</weight><dimensions><length>999.9</length><width>65.4</width><height>32.1</height></dimensions></parcel-characteristics>',
        ];
    }

    #[DataProvider('getValidFormatDollarToCentsAmounts')]
    public function testValidFormatDollarToCentsAmounts(string $value, int $expectedValue): void
    {
        self::assertSame($expectedValue, RatingClient::convertDollarsToCents($value));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function getValidFormatDollarToCentsAmounts(): iterable
    {
        yield 'Two decimals' => ['12.34', 1234];
        yield 'One decimal' => ['12.3', 1230];
        yield 'No decimals' => ['12', 1200];
        yield 'Zero with two decimals' => ['0.00', 0];
        yield 'Zero with one decimal' => ['0.0', 0];
        yield 'Zero with no decimals' => ['0', 0];
        yield 'Has padding' => [' 12.34 ', 1234];
    }

    #[DataProvider('getInvalidFormatDollarToCentsAmounts')]
    public function testInvalidFormatDollarToCentsAmounts(string $value): void
    {
        $this->expectException(CanadaPostException::class);
        RatingClient::convertDollarsToCents($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getInvalidFormatDollarToCentsAmounts(): iterable
    {
        yield 'Empty' => [''];
        yield 'Too many dots' => ['1.2.3'];
        yield 'Too many decimals' => ['1.234'];
        yield 'Negative' => ['-12.34'];
        yield 'Letters' => ['A1.23'];
    }

    private function rating(callable $handler): RatingClient
    {
        return new RatingClient(
            new Transport('https://localhost', 'username', 'password', ['handler' => $handler]),
            '123456789',
        );
    }

    /**
     * Get rating response from provided XML.
     *
     * @return list<PriceQuote>
     */
    private function getRatingResponse(string $xml, int $statusCode = 200): array
    {
        return $this->getRatingTransaction($xml, $statusCode)['result'];
    }

    /**
     * Get the XML request body from a getRates() call.
     */
    private function getSentRequestBody(Destination $destination, Parcel $parcel, string $originPostalCode = 'H0H0H0'): \DOMDocument
    {
        $doc = new \DOMDocument();
        $requestBody = $this->getRatingTransaction(
            FixtureFile::load('DevProgram/get-rates.xml'),
            200,
            $originPostalCode,
            $destination,
            $parcel,
        )['request']->getBody();
        $doc->loadXML((string) $requestBody);
        return $doc;
    }

    /**
     * @return array{
     *     request: RequestInterface,
     *     response: ResponseInterface|null,
     *     error: mixed,
     *     options: array<string, mixed>,
     *     result: list<PriceQuote>,
     * }
     */
    private function getRatingTransaction(
        string $xml,
        int $statusCode = 200,
        string $originPostalCode = 'H0H0H0',
        Destination $destination = new Destination('CA', 'H0H0H0'),
        Parcel $parcel = new Parcel(500),
    ): array {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [
            new Response($statusCode, [], $xml),
        ]);

        // Add key 'result' for response after it passed through send.
        $result = $this->rating($handlerStack)->getRates($originPostalCode, $destination, $parcel);
        $firstTransaction = $historyContainer[0] ?? throw new \LogicException('No request sent.');
        $firstTransaction['result'] = $result;

        return $firstTransaction;
    }
}
