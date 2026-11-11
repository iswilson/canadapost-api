<?php

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Rating Class Tests.
 */
#[CoversClass(Rating::class)]
#[CoversMethod(Rating::class, 'getRates')]
#[Group('Rating')]
class RatingTest extends CanadaPostTestBase
{
    /**
     * The Rating service.
     */
    protected Rating $ratingService;

    /**
     * Build the $ratingService to be tested.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->ratingService = new Rating($this->config);
    }

    /**
     * Test successful rating response.
     */
    public function testValidRatingResponse(): void
    {
        $response = $this->getRatingResponse(file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml'));
        self::assertEquals([
            new PriceQuote('DOM.EP', 'Expedited Parcel', 1021),
            new PriceQuote('DOM.PC', 'Priority Courier', 2520),
            new PriceQuote('DOM.RP', 'Regular Parcel', 1021),
            new PriceQuote('DOM.XP', 'Xpresspost', 1364),
        ], $response);
    }

    public function testEmptyRatingResponse(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('empty response');
        $this->getRatingResponse('');
    }

    public function testInvalidRatingResponse(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('invalid XML');
        $this->getRatingResponse('this is not an XML string');
    }

    public function testValidResponseWithMissingPriceQuotes(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('element is missing');
        $this->getRatingResponse(<<<'EOF'
            <messages>
                <message>
                    <code>7007</code>
                    <description>The weight value is invalid. The weight of each piece must be less than or equal to 30 kg.</description>
                </message>
                <message>
                    <code>1722</code>
                    <description>The options are invalid for the selected Service. Please change the options or select another service.</description>
                </message>
            </messages>
        EOF);
    }

    /**
     * Get rating response from provided XML.
     *
     * @return list<PriceQuote>
     */
    private function getRatingResponse(string $xml): array
    {
        $mock = new MockHandler([
            new Response(200, [], $xml),
        ]);
        $handlerStack = HandlerStack::create($mock);

        return $this->ratingService->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            ['handler' => $handlerStack],
        );
    }

    public function testEmptyPriceQuotes(): void
    {
        $response = $this->getRatingResponse('<?xml version="1.0" encoding="UTF-8"?><price-quotes></price-quotes>');
        self::assertEquals([], $response);
    }

    public function testResponseMissingServiceCode(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('service code');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseEmptyServiceCode(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('service code');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code></service-code>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseMissingServiceName(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('service name');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseEmptyServiceName(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('service name');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <service-name></service-name>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseMissingTotalDue(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('dollar amount');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseEmptyTotalDue(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('dollar amount');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <due></due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst>0.00</pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    public function testResponseMissingTax(): void
    {
        $response = $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
        self::assertSame(0, $response[0]->pstCents);
    }

    public function testResponseEmptyTax(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('dollar amount');
        $this->getRatingResponse(<<<'EOF'
            <price-quotes>
                <price-quote>
                    <service-code>DOM.EP</service-code>
                    <service-name>Expedited Parcel</service-name>
                    <price-details>
                        <due>10.00</due>
                        <taxes>
                            <gst>0.00</gst>
                            <pst></pst>
                            <hst>0.00</hst>
                        </taxes>
                    </price-details>
                </price-quote>
            </price-quotes>
        EOF);
    }

    #[DataProvider('getDestinations')]
    public function testSendsToDestination(Destination $destination, string $expectedXml): void
    {
        $request = $this->getSentRequestBody($destination, new Parcel(500));
        $sentXml = $request->saveXML($request->getElementsByTagName('destination')->item(0));
        self::assertXmlStringEqualsXmlString($expectedXml, $sentXml);
    }

    /** @return iterable<string, array{Destination, string}> */
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
        self::assertXmlStringEqualsXmlString('<origin-postal-code>H0H0H0</origin-postal-code>', $sentXml);
    }

    /**
     * Get the XML request body from a getRates() call.
     */
    private function getSentRequestBody(Destination $destination, Parcel $parcel, string $originPostalCode = 'H0H0H0'): \DOMDocument
    {
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        // Add history middleware that will record each request.
        $historyContainer = [];
        $history = Middleware::history($historyContainer);
        $handlerStack->push($history);

        $this->ratingService->getRates($originPostalCode, $destination, $parcel, ['handler' => $handlerStack]);

        // Get request body from history.
        $requestBody = new \DOMDocument();
        $requestBody->loadXML((string) $historyContainer[0]['request']->getBody());

        return $requestBody;
    }

    #[DataProvider('getValidFormatDecimalInputs')]
    public function testFormatDecimal(int $value, int $decimalPlaces, string $expectedValue): void
    {
        self::assertSame($expectedValue, Rating::formatDecimal($value, $decimalPlaces));
    }

    /** @return iterable<string, array{int, int, string}> */
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
        Rating::formatDecimal($value, $decimalPlaces);
    }

    /** @return iterable<string, array{int, int}> */
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
        self::assertXmlStringEqualsXmlString($expectedXml, $sentXml);
    }

    /** @return iterable<string, array{int, ?int, ?int, ?int, string}> */
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
        self::assertSame($expectedValue, Rating::convertDollarsToCents($value));
    }

    /** @return iterable<string, array{string, int}> */
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
        Rating::convertDollarsToCents($value);
    }

    /** @return iterable<string, array{string}> */
    public static function getInvalidFormatDollarToCentsAmounts(): iterable
    {
        yield 'Empty' => [''];
        yield 'Too many dots' => ['1.2.3'];
        yield 'Too many decimals' => ['1.234'];
        yield 'Negative' => ['-12.34'];
        yield 'Letters' => ['A1.23'];
    }
}
