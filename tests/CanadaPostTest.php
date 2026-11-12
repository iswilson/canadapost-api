<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use iswilson\CanadaPost\CanadaPost;
use iswilson\CanadaPost\Tests\Traits\CaptureRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(CanadaPost::class)]
#[Group('CanadaPost')]
final class CanadaPostTest extends TestCase
{
    use CaptureRequestTrait;

    public function testDefaultValues(): void
    {
        $request = $this->getSentRequest(CanadaPost::devProgram('username', 'password', '123456789'));
        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame('ct.soa-gw.canadapost.ca', $request->getUri()->getHost());
        self::assertSame(0, $body->getElementsByTagName('contract-id')->length);
    }

    public function testArgumentsAreCorrectlyUsed(): void
    {
        $request = $this->getSentRequest(CanadaPost::devProgram('username', 'password', '123456789', '987654321', false));
        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame(sprintf('Basic %s', base64_encode('username:password')), $request->getHeaderLine('Authorization'));
        self::assertSame('123456789', $body->getElementsByTagName('customer-number')->item(0)?->textContent);
        self::assertSame('987654321', $body->getElementsByTagName('contract-id')->item(0)?->textContent);
        self::assertSame('soa-gw.canadapost.ca', $request->getUri()->getHost());
    }
}
