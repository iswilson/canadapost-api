<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating\Destination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Destination::class)]
#[Group('Rating')]
final class DestinationTest extends TestCase
{
    #[DataProvider('getValidCountryCodes')]
    public function testValidCountryCodes(string $countryCode, string $expectedCountryCode): void
    {
        $destination = new Destination($countryCode);
        self::assertSame($expectedCountryCode, $destination->countryCode);
    }

    /** @return iterable<string, array{string, string}> */
    public static function getValidCountryCodes(): iterable
    {
        yield 'Already normalized' => ['AQ', 'AQ'];
        yield 'Lowercase' => ['aq', 'AQ'];
        yield 'Padded' => [' AQ ', 'AQ'];
    }

    #[DataProvider('getInvalidCountryCodes')]
    public function testInvalidCountryCodes(string $countryCode): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Destination($countryCode);
    }

    /** @return iterable<string, array{string}> */
    public static function getInvalidCountryCodes(): iterable
    {
        yield 'Empty' => [''];
        yield 'Numeric' => ['12'];
        yield 'Too long' => ['CAN'];
        yield 'Too short' => ['C'];
    }

    #[DataProvider('getValidCanadianPostalCodes')]
    public function testValidCanadianPostalCodes(string $postalCode, string $expectedPostalCode): void
    {
        $destination = new Destination('CA', $postalCode);
        self::assertSame($expectedPostalCode, $destination->postalCode);
    }

    /** @return iterable<string, array{string, string}> */
    public static function getValidCanadianPostalCodes(): iterable
    {
        yield 'Already normalized' => ['H0H0H0', 'H0H0H0'];
        yield 'Lowercase' => ['h0h0h0', 'H0H0H0'];
        yield 'Padded' => [' H0H0H0 ', 'H0H0H0'];
        yield 'Hyphenated' => ['H0H-0H0', 'H0H0H0'];
        yield 'With space' => ['H0H 0H0', 'H0H0H0'];
    }

    #[DataProvider('getInvalidCanadianPostalCodes')]
    public function testInvalidCanadianPostalCodes(?string $postalCode): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Destination('CA', $postalCode);
    }

    /** @return iterable<string, array{?string}> */
    public static function getInvalidCanadianPostalCodes(): iterable
    {
        yield 'Empty' => [''];
        yield 'Null' => [null];
        yield 'Numeric' => ['123456'];
        yield 'Too short' => ['H0H0H'];
        yield 'Too long' => ['H0H0H0H'];
        yield 'Wrong pattern' => ['HH00H0'];
    }

    #[DataProvider('getValidZipCodes')]
    public function testValidZipCodes(string $zipCode, string $expectedZipCode): void
    {
        $destination = new Destination('US', $zipCode);
        self::assertSame($expectedZipCode, $destination->postalCode);
    }

    /** @return iterable<string, array{string, string}> */
    public static function getValidZipCodes(): iterable
    {
        yield '5 digits' => ['12345', '12345'];
        yield 'Padded' => [' 12345 ', '12345'];
        yield '5+4 digits' => ['12345-6789', '12345-6789'];
        yield '5+4 with space' => ['12345 6789', '12345-6789'];
        yield '5+4 without hyphen' => ['123456789', '12345-6789'];
    }

    #[DataProvider('getInvalidZipCodes')]
    public function testInvalidZipCodes(?string $zipCode): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Destination('US', $zipCode);
    }

    /** @return iterable<string, array{?string}> */
    public static function getInvalidZipCodes(): iterable
    {
        yield 'Empty' => [''];
        yield 'Null' => [null];
        yield 'Letters' => ['ABCDE'];
        yield 'Too short' => ['1234'];
        yield '5+4 too short' => ['12345-678'];
    }

    #[DataProvider('getValidInternationalPostalCodes')]
    public function testValidInternationalPostalCodes(?string $postalCode, ?string $expectedPostalCode): void
    {
        $destination = new Destination('AQ', $postalCode);
        self::assertSame($expectedPostalCode, $destination->postalCode);
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function getValidInternationalPostalCodes(): iterable
    {
        yield 'Empty' => ['', null];
        yield 'Null' => [null, null];
        yield 'Maximum length' => ['A1B2C3D4E5', 'A1B2C3D4E5'];
        yield 'Too long' => ['A1B2C3D4E5F6', null];
        yield 'Padded' => [' A1B2C3D4 ', 'A1B2C3D4'];
        yield 'Separator untouched' => ['A1B2-C3D4', 'A1B2-C3D4'];
        yield 'Whitespace' => ['     ', null];
    }

    public function testValidNormalizeCanadianPostalCode(): void
    {
        self::assertSame('H0H0H0', Destination::normalizeCanadianPostalCode(' H0H-0H0'));
    }

    public function testInvalidNormalizeCanadianPostalCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Destination::normalizeCanadianPostalCode('12345');
    }
}
