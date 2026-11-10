<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating\Parcel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parcel::class)]
#[Group('Rating')]
final class ParcelTest extends TestCase
{
    #[DataProvider('getValidParcelWeights')]
    public function testValidParcelWeights(int $weightGrams, int $expectedWeightGrams): void
    {
        $parcel = new Parcel($weightGrams);
        self::assertSame($expectedWeightGrams, $parcel->weightGrams);
    }

    /** @return iterable<string, array{int, int}> */
    public static function getValidParcelWeights(): iterable
    {
        yield 'Maximum' => [99_999, 99_999];
        yield 'Minimum' => [1, 1];
        yield 'One kilogram' => [1_000, 1_000];
    }

    #[DataProvider('getInvalidParcelWeights')]
    public function testInvalidParcelWeights(int $weightGrams): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Parcel($weightGrams);
    }

    /** @return iterable<string, array{int}> */
    public static function getInvalidParcelWeights(): iterable
    {
        yield 'Over maximum' => [100_000];
        yield 'Zero' => [0];
        yield 'Negative' => [-1];
    }

    public function testDimensionsAreOptional(): void
    {
        $parcel = new Parcel(1);
        self::assertNull($parcel->lengthMm);
        self::assertNull($parcel->widthMm);
        self::assertNull($parcel->heightMm);
        self::assertFalse($parcel->hasDimensions());
    }

    public function testHasDimensions(): void
    {
        $parcel = new Parcel(1, 1, 1, 1);
        self::assertTrue($parcel->hasDimensions());
    }

    #[DataProvider('getValidParcelDimensions')]
    public function testValidParcelDimensions(
        int $lengthMm,
        int $widthMm,
        int $heightMm,
        int $expectedLengthMm,
        int $expectedWidthMm,
        int $expectedHeightMm,
    ): void {
        $parcel = new Parcel(1, $lengthMm, $widthMm, $heightMm);
        self::assertSame($expectedLengthMm, $parcel->lengthMm);
        self::assertSame($expectedWidthMm, $parcel->widthMm);
        self::assertSame($expectedHeightMm, $parcel->heightMm);
    }

    /** @return iterable<string, array{int, int, int, int, int, int}> */
    public static function getValidParcelDimensions(): iterable
    {
        yield 'Maximum size' => [999_9, 999_9, 999_9, 999_9, 999_9, 999_9];
        yield 'Minimum size' => [1, 1, 1, 1, 1, 1];
        yield 'Ten centimeters' => [10_0, 10_0, 10_0, 10_0, 10_0, 10_0];
    }

    #[DataProvider('getMissingParcelDimensions')]
    public function testMissingParcelDimensions(
        ?int $lengthMm,
        ?int $widthMm,
        ?int $heightMm,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new Parcel(1, $lengthMm, $widthMm, $heightMm);
    }

    /** @return iterable<string, array{?int, ?int, ?int}> */
    public static function getMissingParcelDimensions(): iterable
    {
        yield 'Only length' => [3, null, null];
        yield 'Only width' => [null, 3, null];
        yield 'Only height' => [null, null, 3];
        yield 'Missing length' => [null, 3, 3];
        yield 'Missing width' => [3, null, 3];
        yield 'Missing height' => [3, 3, null];
    }

    #[DataProvider('getInvalidParcelDimensions')]
    public function testInvalidParcelDimensions(
        int $lengthMm,
        int $widthMm,
        int $heightMm,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new Parcel(1, $lengthMm, $widthMm, $heightMm);
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function getInvalidParcelDimensions(): iterable
    {
        yield 'Zero length' => [0, 3, 3];
        yield 'Zero width' => [3, 0, 3];
        yield 'Zero height' => [3, 3, 0];
        yield 'Negative length' => [-1, 3, 3];
        yield 'Negative width' => [3, -1, 3];
        yield 'Negative height' => [3, 3, -1];
        yield 'Length over limit' => [1_000_0, 3, 3];
        yield 'Width over limit' => [3, 1_000_0, 3];
        yield 'Height over limit' => [3, 3, 1_000_0];
    }

    #[DataProvider('getUnsortedValidParcelDimensions')]
    public function testParcelDimensionsSorting(
        int $lengthMm,
        int $widthMm,
        int $heightMm,
        int $expectedLengthMm,
        int $expectedWidthMm,
        int $expectedHeightMm,
    ): void {
        $parcel = new Parcel(1, $lengthMm, $widthMm, $heightMm);
        self::assertSame($expectedLengthMm, $parcel->lengthMm);
        self::assertSame($expectedWidthMm, $parcel->widthMm);
        self::assertSame($expectedHeightMm, $parcel->heightMm);
    }

    /** @return iterable<string, array{int, int, int, int, int, int}> */
    public static function getUnsortedValidParcelDimensions(): iterable
    {
        yield 'Already sorted' => [3, 2, 1, 3, 2, 1];
        yield 'Wrong order' => [3, 1, 2, 3, 2, 1];
        yield 'Reversed order' => [1, 2, 3, 3, 2, 1];
        yield 'Two equal sides (long)' => [2, 3, 3, 3, 3, 2];
        yield 'Two equal sides (short)' => [2, 2, 3, 3, 2, 2];
        yield 'All sides equal' => [3, 3, 3, 3, 3, 3];
    }

    public function testMissingDimensionsAreReported(): void
    {
        $this->expectExceptionMessage('missing: width, height');
        new Parcel(1, 3);
    }
}
