<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tracking;

/**
 * Defines a Canada Post tracking summary for a parcel.
 *
 * Canada Post doesn't provide time zones with date fields. Dates and times are
 * assumed to be local to wherever the event takes place (e.g. $mailedOnDate
 * would be the sender's timezone). Values are returned by the library as UTC
 * but should be displayed as-is.
 */
final readonly class PinSummary
{
    public function __construct(
        /** Parcel Identification Number (PIN). */
        public string $pin,
        /** First 3 characters of the origin Postal Code for Canadian destinations. */
        public ?string $originPostalId = null,
        /** First 3 characters of the destination Postal Code for parcels to be delivered in Canada. */
        public ?string $destinationPostalId = null,
        /** The 2-character province code for the destination. */
        public ?string $destinationProvince = null,
        /** Canada Post service name. */
        public ?string $serviceName = null,
        /** The date of the first event for the item. */
        public ?\DateTimeImmutable $mailedOnDate = null,
        /** The date the item is expected to reach the destination address for addresses in Canada. */
        public ?\DateTimeImmutable $expectedDeliveryDate = null,
        /** The date the item was delivered. */
        public ?\DateTimeImmutable $actualDeliveryDate = null,
        /** Status of completed delivery options. */
        public ?DeliveryOptionCompleted $deliveryOptionCompletedInd = null,
        /** The time stamp of the most recent significant event. */
        public ?\DateTimeImmutable $eventDateTime = null,
        /** A brief description of the event. */
        public ?string $eventDescription = null,
        /** The date that a delivery was attempted. */
        public ?\DateTimeImmutable $attemptedDate = null,
        /** What type of event is represented for this item. */
        public ?string $eventType = null,
        /** The location where the event took place (if scan or delivery). */
        public ?string $eventLocation = null,
    ) {}
}
