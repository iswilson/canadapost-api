<?php

namespace CanadaPost\Tests;

use CanadaPost\Returns;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Returns Class Tests.
 */
#[CoversClass(Returns::class)]
#[CoversMethod(Returns::class, 'createAuthorizedReturn')]
#[CoversMethod(Returns::class, 'createOpenReturn')]
#[CoversMethod(Returns::class, 'getOpenReturn')]
#[CoversMethod(Returns::class, 'getOpenReturnDetails')]
#[CoversMethod(Returns::class, 'getNextOpenReturnArtifact')]
#[Group('Returns')]
class ReturnsTest extends CanadaPostTestBase
{

    /**
     * The Returns service.
     *
     * @var Returns
     */
    protected $returnsService;

    /**
     * Build the $returnsService to be tested.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->returnsService = new Returns($this->config);
    }

    /**
     * Test the POST request to create an authorized return from Canada Post.
     */
    public function testCreateAuthorizedReturn()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/authreturn-response-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $return = $this->returnsService->createAuthorizedReturn(
            $this->mockAddress(),
            $this->mockAddress(),
            $this->mockParcel(),
            ['handler' => $handler]
        );

        $this->assertTrue(is_array($return));

        $return_info = $return['authorized-return-info'];

        $this->assertEquals('12345678901234', $return_info['tracking-pin']);

        $links = $return_info['links'];
        $this->assertTrue(is_array($links));

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/ers/artifact/76108cb5192002d5/21238/0',
            $links['link']['@attributes']['href']);
    }

    /**
     * Test the POST request to create an open return from Canada Post.
     */
    public function testCreateOpenReturn()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/openreturn-response-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $return = $this->returnsService->createOpenReturn(
            $this->mockAddress(),
            $this->mockParcel(),
            ['handler' => $handler]
        );

        $this->assertTrue(is_array($return));

        $return_info = $return['open-return-info'];

        $this->assertEquals('10', $return_info['artifacts-remaining']);

        $links = $return_info['links'];
        $this->assertTrue(is_array($links));

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649',
            $links['link'][0]['@attributes']['href']);
        $this->assertEquals('self', $links['link'][0]['@attributes']['rel']);

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649/details',
            $links['link'][1]['@attributes']['href']);
        $this->assertEquals('details', $links['link'][1]['@attributes']['rel']);

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649/artifact',
            $links['link'][2]['@attributes']['href']);
        $this->assertEquals('nextArtifact',
            $links['link'][2]['@attributes']['rel']);

    }


    /**
     * Test the POST request to retrieve an open return from Canada Post.
     */
    public function testGetOpenReturn()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/openreturn-response-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $return = $this->returnsService->getOpenReturn(
            '',
            ['handler' => $handler]
        );

        $this->assertTrue(is_array($return));

        $return_info = $return['open-return-info'];

        $this->assertEquals('10', $return_info['artifacts-remaining']);

        $links = $return_info['links'];
        $this->assertTrue(is_array($links));

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649',
            $links['link'][0]['@attributes']['href']);
        $this->assertEquals('self', $links['link'][0]['@attributes']['rel']);

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649/details',
            $links['link'][1]['@attributes']['href']);
        $this->assertEquals('details', $links['link'][1]['@attributes']['rel']);

        $this->assertEquals('https://ct.soa-gw.canadapost.ca/rs/0007023211/0007023211/openreturn/349641323786705649/artifact',
            $links['link'][2]['@attributes']['href']);
        $this->assertEquals('nextArtifact',
            $links['link'][2]['@attributes']['rel']);
    }

    /**
     * Test the POST request to retrieve an open return from Canada Post.
     */
    public function testGetOpenReturnDetails()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/openreturn-details-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $return = $this->returnsService->getOpenReturnDetails(
            '',
            ['handler' => $handler]
        );

        $this->assertTrue(is_array($return));
        $this->assertTrue(is_array($return['open-return-details']));

        $return_details = $return['open-return-details']['open-return'];

        $this->assertEquals('10', $return['open-return-details']['artifacts-remaining']);

        $this->assertEquals('10', $return_details['max-number-of-artifacts']);
        $this->assertEquals('DOM.EP', $return_details['service-code']);

        $this->assertEquals('23 jardin private', $return_details['receiver']['domestic-address']['address-line-1']);
        $this->assertEquals('Ottawa', $return_details['receiver']['domestic-address']['city']);
        $this->assertEquals('ON', $return_details['receiver']['domestic-address']['province']);
        $this->assertEquals('K1K4T3', $return_details['receiver']['domestic-address']['postal-code']);

        $this->assertEquals('8.5x11', $return_details['print-preferences']['output-format']);
        $this->assertEquals('PDF', $return_details['print-preferences']['encoding']);
        $this->assertEquals('false', $return_details['print-preferences']['show-packing-instructions']);

        $this->assertEquals('0012345678', $return_details['settlement-info']['contract-id']);
    }

    /**
     * Test the POST request to retrieve the next open return artifact from Canada Post.
     */
    public function testGetNextOpenReturnArtifact()
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/canadapost.pdf');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);

        $response = $this->returnsService->getArtifact('the/endpoint', ['handler' => $handler]);

        // Compares the pdfs.
        $this->assertEquals(0, strcmp($body, $response->getContents()));
    }

    protected function mockAddress()
    {
        return [
            'name' => 'John Smith',
            'company' => 'ACME',
            'domestic-address' => [
                'address-line-1' => '123 Main St',
                'city' => 'Ottawa',
                'province' => 'ON',
                'postal-code' => 'K1A0B1',
            ],
        ];
    }
}
