<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

/**
 * Defines a Canada Post quote for a single service.
 */
final readonly class PriceQuote
{
    public function __construct(
        // Service information.
        public string $serviceCode,
        public string $serviceName,
        /** Total due (cents) including taxes. */
        public int $totalDueCents,
        // Individual taxes (cents).
        public int $gstCents = 0,
        public int $pstCents = 0,
        public int $hstCents = 0,
    ) {}

    /**
     * Total taxes (cents).
     */
    public function totalTaxesCents(): int
    {
        return $this->gstCents + $this->pstCents + $this->hstCents;
    }

    /**
     * Total due before tax (cents).
     *
     * Includes: base cost, options, adjustments (surcharges and discounts).
     */
    public function totalDueBeforeTaxesCents(): int
    {
        return $this->totalDueCents - $this->totalTaxesCents();
    }
}
