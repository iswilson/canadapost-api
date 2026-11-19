<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\CanadaPost;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Tests\Traits\HttpMockHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(CanadaPost::class)]
#[Group('CanadaPost')]
final class CanadaPostTest extends TestCase
{
    use HttpMockHandlerTrait;

    public function testDefaultValues(): void
    {
        $history = [];
        $handlerStack = $this->handlerStack($history, [
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')),
        ]);

        CanadaPost::devProgram('username', 'password', '123456789', handler: $handlerStack)
            ->rating()
            ->getRates('H0H0H0', new Destination('CA', 'H0H0H0'), new Parcel(500));

        $request = $history[0]['request'];
        self::assertSame('ct.soa-gw.canadapost.ca', $request->getUri()->getHost());

        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame(0, $body->getElementsByTagName('contract-id')->length);
    }

    public function testTimeoutIsOverridden(): void
    {
        $history = [];
        $handlerStack = $this->handlerStack($history, [
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')),
        ]);

        CanadaPost::devProgram('username', 'password', '123456789', timeout: 5, handler: $handlerStack)
            ->rating()
            ->getRates('K1A0B1', new Destination('CA', 'H0H0H0'), new Parcel(500));

        $options = $history[0]['options'];
        self::assertSame(5.0, $options['timeout']);
    }
}
