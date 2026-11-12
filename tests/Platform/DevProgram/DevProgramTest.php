<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Platform\DevProgram\DevProgram;
use iswilson\CanadaPost\Tests\Traits\CaptureRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(DevProgram::class)]
#[Group('DevProgram')]
final class DevProgramTest extends TestCase
{
    use CaptureRequestTrait;

    public function testUsernameIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username is a required');
        new DevProgram('', 'password', '123456789', null, true);
    }

    public function testPasswordIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password is a required');
        new DevProgram('username', '', '123456789', null, true);
    }

    public function testCustomerNumberIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer number is a required');
        new DevProgram('username', 'password', '', null, true);
    }

    #[DataProvider('getInvalidCustomerNumbers')]
    public function testInvalidCustomerNumbers(string $customerNumber): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer number is invalid');
        new DevProgram('username', 'password', $customerNumber, null, true);
    }

    /** @return iterable<string, array{string}> */
    public static function getInvalidCustomerNumbers(): iterable
    {
        yield 'Too long' => ['12345678901'];
        yield 'Has letters' => ['A1B2C3D4'];
    }

    #[DataProvider('getInvalidContractIds')]
    public function testInvalidContractId(string $contractId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contract ID is invalid');
        new DevProgram('username', 'password', '123456789', $contractId, true);
    }

    /** @return iterable<string, array{string}> */
    public static function getInvalidContractIds(): iterable
    {
        yield 'Too long' => ['12345678901'];
        yield 'Has letters' => ['A1B2C3D4'];
    }

    public function testCredentialsAreTrimmedAndPresent(): void
    {
        $request = $this->getSentRequest(new DevProgram(' username ', ' password ', '123456789', null, true));
        self::assertSame(sprintf('Basic %s', base64_encode('username:password')), $request->getHeaderLine('Authorization'));
    }

    public function testCustomerNumberAndContractIdAreTrimmedAndPresent(): void
    {
        $request = $this->getSentRequest(new DevProgram('username', 'password', ' 123456789 ', ' 987654321 ', true));
        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame('123456789', $body->getElementsByTagName('customer-number')->item(0)?->textContent);
        self::assertSame('987654321', $body->getElementsByTagName('contract-id')->item(0)?->textContent);
    }

    #[DataProvider('getEmptyOrNullContractIds')]
    public function testContractIdIsAbsentIfEmptyOrNull(?string $contractId): void
    {
        $request = $this->getSentRequest(new DevProgram('username', 'password', '123456789', $contractId, true));
        $body = new \DOMDocument();
        $body->loadXML((string) $request->getBody());
        self::assertSame(0, $body->getElementsByTagName('contract-id')->length);
    }

    /** @return iterable<string, array{?string}> */
    public static function getEmptyOrNullContractIds(): iterable
    {
        yield 'Empty' => [''];
        yield 'Null' => [null];
        yield 'All spaces' => ['   '];
    }

    public function testSandboxModeUsesCorrectHost(): void
    {
        $request = $this->getSentRequest(new DevProgram('username', 'password', '123456789', null, true));
        self::assertSame('ct.soa-gw.canadapost.ca', $request->getUri()->getHost());
    }

    public function testProductionModeUsesCorrectHost(): void
    {
        $request = $this->getSentRequest(new DevProgram('username', 'password', '123456789', null, false));
        self::assertSame('soa-gw.canadapost.ca', $request->getUri()->getHost());
    }
}
