<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tracking Class Tests.
 */
#[CoversClass(Tracking::class)]
#[CoversMethod(Tracking::class, 'getSummary')]
#[CoversMethod(Tracking::class, 'getDetails')]
#[Group('Tracking')]
class TrackingTest extends TestCase
{
    /**
     * The Tracking service.
     */
    protected Tracking $trackingService;

    /**
     * Build the $trackingService to be tested.
     */
    public function setUp(): void
    {
        $this->trackingService = new Tracking([
            'username' => 'username',
            'password' => 'password',
            'customer_number' => 'customer_number',
            'contract_id' => 'contract_id',
        ]);
    }

    public function testGetSummary(): void
    {
        $body = file_get_contents(__DIR__ . '/Mocks/tracking-summary-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $response = $this->trackingService->getSummary(
            '',
            'pin',
            ['handler' => $handler],
        );

        // Check response.
        self::assertTrue(is_array($response['tracking-summary']));
        self::assertTrue(is_array($response['tracking-summary']['pin-summary']));

        // Get the summary.
        $summary = $response['tracking-summary']['pin-summary'];

        // Expedited Parcel.
        self::assertEquals('1681334332936901', $summary['pin']);
        self::assertEquals('S6V', $summary['origin-postal-id']);
        self::assertEquals('2010-01-05', $summary['mailed-on-date']);
        self::assertEquals('2010-01-06', $summary['actual-delivery-date']);
        self::assertEquals('2', $summary['delivery-option-completed-ind']);
        self::assertEquals('20100106:084923', $summary['event-date-time']);
        self::assertEquals('Paiement CR - SPL', $summary['event-description']);
        self::assertEquals('2010-01-06', $summary['attempted-date']);
        self::assertEquals('OUT', $summary['event-type']);

    }

    public function testGetDetails(): void
    {
        $body = file_get_contents(__DIR__ . '/Mocks/tracking-details-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $response = $this->trackingService->getDetails(
            '',
            'dnc',
            ['handler' => $handler],
        );

        // Check response.
        self::assertTrue(is_array($response['tracking-detail']));

        // Get the summary.
        $details = $response['tracking-detail'];
        self::assertEquals('1371134583769923', $details['pin']);
        self::assertEquals('1', $details['active-exists']);
        self::assertEquals('2011-02-11', $details['changed-expected-date']);
        self::assertEquals('G1K4M7', $details['destination-postal-id']);
        self::assertEquals('2011-02-01', $details['expected-delivery-date']);
        self::assertEquals('Customer addressing error found; attempting to correct', $details['changed-expected-delivery-reason']);
        self::assertEquals('0001234567', $details['mailed-by-customer-number']);
        self::assertEquals('0008765432', $details['mailed-on-behalf-of-customer-number']);
        self::assertEquals('Xpresspost', $details['service-name']);
        self::assertEquals('Xpresspost', $details['service-name-2']);
        self::assertEquals('955-0398', $details['customer-ref-1']);
        self::assertEquals('true', $details['signature-image-exists']);
        self::assertEquals('false', $details['suppress-signature']);

        // Delivery Options.
        $delivery_options = $details['delivery-options'];
        self::assertTrue(is_array($delivery_options));
        self::assertTrue(is_array($delivery_options['item']));
        self::assertEquals('CH_SGN_OPTION', $delivery_options['item']['delivery-option']);
        self::assertEquals('Signature Required', $delivery_options['item']['delivery-option-description']);

        // Get the events.
        $events = $response['tracking-detail']['significant-events'];

        self::assertEquals('20', $events['occurrence'][0]['event-identifier']);
        self::assertEquals('2011-02-03', $events['occurrence'][0]['event-date']);
        self::assertEquals('11:59:59', $events['occurrence'][0]['event-time']);
        self::assertEquals('EST', $events['occurrence'][0]['event-time-zone']);
        self::assertEquals('Signature image recorded for Online viewing', $events['occurrence'][0]['event-description']);
        self::assertEquals('SAINTE-FOY', $events['occurrence'][0]['event-site']);
        self::assertEquals('QC', $events['occurrence'][0]['event-province']);

        self::assertEquals('0174', $events['occurrence'][1]['event-identifier']);
        self::assertEquals('2011-02-03', $events['occurrence'][1]['event-date']);
        self::assertEquals('08:27:43', $events['occurrence'][1]['event-time']);
        self::assertEquals('EST', $events['occurrence'][1]['event-time-zone']);
        self::assertEquals('Item out for delivery', $events['occurrence'][1]['event-description']);
        self::assertEquals('SAINTE-FOY', $events['occurrence'][1]['event-site']);
        self::assertEquals('QC', $events['occurrence'][1]['event-province']);

        self::assertEquals('0100', $events['occurrence'][2]['event-identifier']);
        self::assertEquals('2011-02-02', $events['occurrence'][2]['event-date']);
        self::assertEquals('14:45:48', $events['occurrence'][2]['event-time']);
        self::assertEquals('EST', $events['occurrence'][2]['event-time-zone']);
        self::assertEquals('Item processed at postal facility', $events['occurrence'][2]['event-description']);
        self::assertEquals('QUEBEC', $events['occurrence'][2]['event-site']);
        self::assertEquals('QC', $events['occurrence'][2]['event-province']);

        self::assertEquals('0173', $events['occurrence'][3]['event-identifier']);
        self::assertEquals('2011-02-02', $events['occurrence'][3]['event-date']);
        self::assertEquals('06:19:57', $events['occurrence'][3]['event-time']);
        self::assertEquals('EST', $events['occurrence'][3]['event-time-zone']);
        self::assertEquals('Customer addressing error found; attempting to correct. Possible delay', $events['occurrence'][3]['event-description']);
        self::assertEquals('QUEBEC', $events['occurrence'][3]['event-site']);
        self::assertEquals('QC', $events['occurrence'][3]['event-province']);

        self::assertEquals('1496', $events['occurrence'][4]['event-identifier']);
        self::assertEquals('2011-02-01', $events['occurrence'][4]['event-date']);
        self::assertEquals('07:59:52', $events['occurrence'][4]['event-time']);
        self::assertEquals('EST', $events['occurrence'][4]['event-time-zone']);
        self::assertEquals('Item successfully delivered', $events['occurrence'][4]['event-description']);
        self::assertEquals('QUEBEC', $events['occurrence'][4]['event-site']);
        self::assertEquals('QC', $events['occurrence'][4]['event-province']);

        self::assertEquals('20', $events['occurrence'][5]['event-identifier']);
        self::assertEquals('2011-02-01', $events['occurrence'][5]['event-date']);
        self::assertEquals('07:59:52', $events['occurrence'][5]['event-time']);
        self::assertEquals('EST', $events['occurrence'][5]['event-time-zone']);
        self::assertEquals('Signature image recorded for Online viewing', $events['occurrence'][5]['event-description']);
        self::assertEquals('QUEBEC', $events['occurrence'][5]['event-site']);
        self::assertEquals('QC', $events['occurrence'][5]['event-province']);
    }

    public function testGetSummaryInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type: "wrong". Supported types are "pin" and "dnc".');
        $mock = new MockHandler([
            new Response(200),
        ]);
        $handler = HandlerStack::create($mock);
        $this->trackingService->getSummary(
            '',
            'wrong',
            ['handler' => $handler],
        );
    }

    public function testGetDetailsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type: "wrong". Supported types are "pin" and "dnc".');
        $mock = new MockHandler([
            new Response(200),
        ]);
        $handler = HandlerStack::create($mock);
        $this->trackingService->getDetails(
            '',
            'wrong',
            ['handler' => $handler],
        );
    }
}
