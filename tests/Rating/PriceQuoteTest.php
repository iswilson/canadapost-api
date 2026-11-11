<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Rating;

use iswilson\CanadaPost\Rating\PriceQuote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceQuote::class)]
#[Group('Rating')]
final class PriceQuoteTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $priceQuote = new PriceQuote('code', 'service', 100);
        self::assertSame(0, $priceQuote->gstCents);
        self::assertSame(0, $priceQuote->pstCents);
        self::assertSame(0, $priceQuote->hstCents);
        self::assertSame(100, $priceQuote->totalDueBeforeTaxesCents());
    }

    public function testCalculateTotalTaxes(): void
    {
        $priceQuote = new PriceQuote('code', 'service', 100, 1, 2, 3);
        self::assertSame(6, $priceQuote->totalTaxesCents());
    }

    public function testCalculateTotalDueBeforeTaxes(): void
    {
        $priceQuote = new PriceQuote('code', 'service', 106, 1, 2, 3);
        self::assertSame(100, $priceQuote->totalDueBeforeTaxesCents());
    }
}
