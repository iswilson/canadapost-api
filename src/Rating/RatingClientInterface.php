<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

/**
 * Rating services for a Canada Post platform.
 *
 * This interface is not intended to be implemented outside of this library. New
 * methods may be added as more endpoints are supported.
 *
 * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/rating/default.jsf
 */
interface RatingClientInterface
{
    /**
     * Get a list of shipping services and rates for a given parcel.
     *
     * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/rating/getrates/default.jsf
     *
     * @param string $originPostalCode
     *   Origin postal code.
     * @param Destination $destination
     *   Destination country and postal/ZIP code.
     * @param Parcel $parcel
     *   Parcel characteristics (weight, length, width, height).
     * @param list<string> $optionCodes
     *   Shipment options.
     * @param list<string> $serviceCodes
     *   Specific services to be rated.
     *
     * @return list<PriceQuote>
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function getRates(
        string $originPostalCode,
        Destination $destination,
        Parcel $parcel,
        array $optionCodes = [],
        array $serviceCodes = [],
    ): array;
}
