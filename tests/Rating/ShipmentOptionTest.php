<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating\ShipmentOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipmentOption::class)]
class ShipmentOptionTest extends TestCase
{
    #[DataProvider('getValidCodes')]
    public function testValidCodes(string $code, string $expectedCode): void
    {
        $shipmentOption = new ShipmentOption($code);
        self::assertSame($expectedCode, $shipmentOption->code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getValidCodes(): iterable
    {
        yield 'Normal' => ['SO', 'SO'];
        yield 'Lowercase' => ['so', 'SO'];
        yield 'With spaces' => ['  SO   ', 'SO'];
    }

    public function testInvalidCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported option code');
        new ShipmentOption('test');
    }

    #[DataProvider('getCodesWithAmountUnsupported')]
    public function testAmountUnsupported(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount is unsupported');
        new ShipmentOption($code, 100);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getCodesWithAmountUnsupported(): iterable
    {
        yield 'Signature' => ['SO'];
        yield 'Proof of Age Required - 18' => ['PA18'];
        yield 'Proof of Age Required - 19' => ['PA19'];
        yield 'Card for pickup' => ['HFP'];
        yield 'Do not safe drop' => ['DNS'];
        yield 'Leave at door - do not card' => ['LAD'];
    }

    #[DataProvider('getCodesWithAmountRequired')]
    public function testAmountRequired(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount is required');
        new ShipmentOption($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function getCodesWithAmountRequired(): iterable
    {
        yield 'Coverage' => ['COV'];
        yield 'Collect on delivery' => ['COD'];
    }

    public function testMinMaxAmounts(): void
    {
        $shipmentOption = new ShipmentOption('COV', 1);
        self::assertSame('COV', $shipmentOption->code);
        self::assertSame(1, $shipmentOption->amountCents);

        $shipmentOption = new ShipmentOption('COV', 99999999);
        self::assertSame('COV', $shipmentOption->code);
        self::assertSame(99999999, $shipmentOption->amountCents);
    }

    #[DataProvider('getInvalidAmounts')]
    public function testInvalidAmounts(int $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('option must be between');
        new ShipmentOption('COV', $amount);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function getInvalidAmounts(): iterable
    {
        yield 'Zero' => [0];
        yield 'Negative' => [-1];
        yield 'Over maximum' => [100000000];
    }

    public function testDedupeWithDuplicates(): void
    {
        $codes = ShipmentOption::unique([
            new ShipmentOption('COV', 100),
            new ShipmentOption('COV', 100),
            new ShipmentOption('SO'),
            new ShipmentOption('SO'),
        ]);
        self::assertCount(2, $codes);
        self::assertEquals(new ShipmentOption('COV', 100), $codes[0]);
        self::assertEquals(new ShipmentOption('SO'), $codes[1]);
    }

    public function testDedupeWithDuplicatesDifferentAmounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('appears twice with differing amounts');
        ShipmentOption::unique([
            new ShipmentOption('COV', 100),
            new ShipmentOption('COV', 200),
        ]);
    }
}
