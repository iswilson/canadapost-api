<?php

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Rating;
use iswilson\CanadaPost\Rating\Destination;
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
     *
     * @var Rating
     */
    protected $ratingService;

    /**
     * Build the $ratingService to be tested.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->ratingService = new Rating($this->config);
    }

    /**
     * Test the POST request to get rates from Canada Post.
     */
    public function testGetRates()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/rating-response-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $response = $this->ratingService->getRates('K1A0B1', new Destination('CA', 'H0H0H0'), 1, ['handler' => $handler]);

        // Check response.
        $this->assertTrue(is_array($response['price-quotes']));

        // Test the rates.
        $rates = $response['price-quotes']['price-quote'];
        $this->assertTrue(is_array($rates));

        // Expedited Parcel.
        $this->assertTrue(is_array($rates[0]));
        $this->assertEquals('DOM.EP', $rates[0]['service-code']);
        $this->assertEquals('Expedited Parcel', $rates[0]['service-name']);
        $this->assertTrue(is_array($rates[0]['price-details']));
        $this->assertEquals('9.59', $rates[0]['price-details']['base']);

        // Priority Courier.
        $this->assertTrue(is_array($rates[1]));
        $this->assertEquals('DOM.PC', $rates[1]['service-code']);
        $this->assertEquals('Priority Courier', $rates[1]['service-name']);
        $this->assertTrue(is_array($rates[1]['price-details']));
        $this->assertEquals('22.64', $rates[1]['price-details']['base']);

        // Priority Courier.
        $this->assertTrue(is_array($rates[2]));
        $this->assertEquals('DOM.RP', $rates[2]['service-code']);
        $this->assertEquals('Regular Parcel', $rates[2]['service-name']);
        $this->assertTrue(is_array($rates[2]['price-details']));
        $this->assertEquals('9.59', $rates[2]['price-details']['base']);

        // Priority Courier.
        $this->assertTrue(is_array($rates[3]));
        $this->assertEquals('DOM.XP', $rates[3]['service-code']);
        $this->assertEquals('Xpresspost', $rates[3]['service-name']);
        $this->assertTrue(is_array($rates[3]['price-details']));
        $this->assertEquals('12.26', $rates[3]['price-details']['base']);
    }

    #[DataProvider('getDestinations')]
    public function testSendsToDestination(Destination $destination, string $expectedXml): void
    {
        $request = $this->getSentRequestBody($destination);
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
        $request = $this->getSentRequestBody(new Destination('CA', 'H0H0H0'), ' h0h-0h0 ');
        $sentXml = $request->saveXML($request->getElementsByTagName('origin-postal-code')->item(0));
        self::assertSame('<origin-postal-code>H0H0H0</origin-postal-code>', $sentXml);
    }

    /**
     * Get the XML request body from a getRates() call.
     */
    private function getSentRequestBody(Destination $destination, string $originPostalCode = 'H0H0H0'): \DOMDocument
    {
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        // Add history middleware that will record each request.
        $historyContainer = [];
        $history = Middleware::history($historyContainer);
        $handlerStack->push($history);

        $this->ratingService->getRates($originPostalCode, $destination, 1, ['handler' => $handlerStack]);

        // Get request body from history.
        $requestBody = new \DOMDocument();
        $requestBody->loadXML((string) $historyContainer[0]['request']->getBody());

        return $requestBody;
    }
}
