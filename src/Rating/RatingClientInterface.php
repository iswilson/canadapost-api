<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;

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
     * Canada Post specific service codes.
     *
     * @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/services/rating/getrates/default.jsf
     */
    final public const array SERVICE_CODES = [
        'DOM.EP' => 'Expedited Parcel',
        'DOM.RP' => 'Regular Parcel',
        'DOM.PC' => 'Priority',
        'DOM.XP' => 'Xpresspost',
        'DOM.XP.CERT' => 'Xpresspost Certified',
        'DOM.LIB' => 'Library Materials',
        'USA.EP' => 'Expedited Parcel USA',
        'USA.SP.AIR' => 'Small Packet USA Air',
        'USA.TP' => 'Tracked Packet - USA',
        'USA.TP.LVM' => 'Tracked Packet - USA (LVM)',
        'USA.XP' => 'Xpresspost USA',
        'INT.XP' => 'Xpresspost International',
        'INT.IP.AIR' => 'International Parcel Air',
        'INT.IP.SURF' => 'International Parcel Surface',
        'INT.SP.AIR' => 'Small Packet International Air',
        'INT.SP.SURF' => 'Small Packet International Surface',
        'INT.TP' => 'Tracked Packet - International',
    ];

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
     * @param list<ShipmentOption> $shipmentOptions
     *   Shipment options.
     * @param list<string> $serviceCodes
     *   Specific services to be rated.
     * @param \DateTimeImmutable|null $expectedMailingDate
     *   Expected mailing date for the parcel. Only the date is sent, which
     *   should be local to the origin postal code.
     *
     * @return list<PriceQuote>
     *
     * @throws CanadaPostException
     * @throws InvalidArgumentException
     */
    public function getRates(
        string $originPostalCode,
        Destination $destination,
        Parcel $parcel,
        array $shipmentOptions = [],
        array $serviceCodes = [],
        ?\DateTimeImmutable $expectedMailingDate = null,
    ): array;
}
