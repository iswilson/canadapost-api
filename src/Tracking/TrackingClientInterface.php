<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tracking;

/**
 * Tracking services for a Canada Post platform.
 *
 * This interface is not intended to be implemented outside of this library. New
 * methods may be added as more endpoints are supported.
 *
 * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/tracking/default.jsf
 */
interface TrackingClientInterface
{
    /**
     * Get the most recent/significant tracking event for a parcel.
     *
     * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/tracking/trackingsummary.jsf
     *
     * @param string $pin
     *   Parcel Identification Number (PIN)
     *
     * @return \iswilson\CanadaPost\Tracking\PinSummary
     *   Canada Post tracking summary.
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function getSummary(string $pin): PinSummary;
}
