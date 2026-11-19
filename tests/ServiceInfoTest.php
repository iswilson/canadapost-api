<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\ServiceInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Service Info Class Tests.
 */
#[CoversClass(ServiceInfo::class)]
#[CoversMethod(ServiceInfo::class, 'getServiceInfo')]
#[Group('ServiceInfo')]
class ServiceInfoTest extends TestCase
{
    /**
     * The ServiceInfo service.
     */
    protected ServiceInfo $serviceInfoService;

    /**
     * Build the $serviceInfoService to be tested.
     */
    public function setUp(): void
    {
        $this->serviceInfoService = new ServiceInfo([
            'username' => 'username',
            'password' => 'password',
            'customer_number' => 'customer_number',
            'contract_id' => 'contract_id',
        ]);
    }

    /**
     * Test the request to get service info from Canada Post.
     */
    public function testGetServiceInfo(): void
    {
        $body = file_get_contents(__DIR__
            . '/Mocks/service-info-success.xml');
        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);
        $handler = HandlerStack::create($mock);
        $response = $this->serviceInfoService->getServiceInfo(
            ['handler' => $handler],
        );

        // Check response.
        self::assertTrue(is_array($response['info-messages']));

        $info = $response['info-messages']['info-message'];
        self::assertEquals('SO', $info['message-type']);
        self::assertEquals('Please note that we will be performing scheduled maintenance from Saturday, Oct. 12 at 9 p.m. to Sunday, Oct. 13 at 4 a.m. EDT. During this time, Canada Post web services will not be available.', $info['message-text']);
        self::assertEquals('2013-10-12T21:00:00-05:00', $info['from-datetime']);
        self::assertEquals('2013-10-13T04:00:00-05:00', $info['to-datetime']);
    }
}
