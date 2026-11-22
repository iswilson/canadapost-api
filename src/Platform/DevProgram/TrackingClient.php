<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Tracking\DeliveryOptionCompleted;
use iswilson\CanadaPost\Tracking\PinSummary;
use iswilson\CanadaPost\Tracking\TrackingClientInterface;

final readonly class TrackingClient implements TrackingClientInterface
{
    public function __construct(
        private Transport $transport,
    ) {}

    public function getSummary(string $pin): PinSummary
    {
        $pin = self::normalizePin($pin);

        $response = $this->transport->send(
            'GET',
            sprintf('vis/track/pin/%s/summary', rawurlencode($pin)),
            [
                'Accept-Language' => 'en-CA',
                'Accept' => 'application/vnd.cpc.track-v2+xml',
            ],
        );

        $doc = XmlResponse::toDOMDocument($response, 'tracking-summary');

        $pinSummaries = self::parseTrackingSummary($doc);
        if ($pinSummaries === []) {
            throw new CanadaPostException('Canada Post API returned an empty tracking summary.');
        }

        return $pinSummaries[0];
    }

    /**
     * Parse an XML response into an array of PinSummary.
     *
     * @return list<PinSummary>
     *
     * @throws CanadaPostException
     */
    private static function parseTrackingSummary(\DOMDocument $doc): array
    {
        $pinSummaries = [];

        $nodes = $doc->getElementsByTagName('pin-summary');
        foreach ($nodes as $node) {
            $pin = XmlResponse::extractText($node, 'pin');
            if ($pin === null) {
                throw new CanadaPostException('Canada Post API returned an empty PIN.');
            }

            $pinSummaries[] = new PinSummary(
                pin: $pin,
                originPostalId: XmlResponse::extractText($node, 'origin-postal-id'),
                destinationPostalId: XmlResponse::extractText($node, 'destination-postal-id'),
                destinationProvince: XmlResponse::extractText($node, 'destination-province'),
                serviceName: XmlResponse::extractText($node, 'service-name'),
                mailedOnDate: XmlResponse::extractDateTime($node, 'mailed-on-date'),
                expectedDeliveryDate: XmlResponse::extractDateTime($node, 'expected-delivery-date'),
                actualDeliveryDate: XmlResponse::extractDateTime($node, 'actual-delivery-date'),
                deliveryOptionCompletedInd: DeliveryOptionCompleted::tryFrom(
                    XmlResponse::extractText($node, 'delivery-option-completed-ind') ?? '',
                ),
                eventDateTime: XmlResponse::extractDateTime($node, 'event-date-time', '!Ymd:His'),
                eventDescription: XmlResponse::extractText($node, 'event-description'),
                attemptedDate: XmlResponse::extractDateTime($node, 'attempted-date'),
                eventType: XmlResponse::extractText($node, 'event-type'),
                eventLocation: XmlResponse::extractText($node, 'event-location'),
            );
        }

        return $pinSummaries;
    }

    private static function normalizePin(string $pin): string
    {
        // Remove leading/trailing whitespace + convert to uppercase.
        $pin = strtoupper(trim($pin));

        // Must be 12, 13 or 16 letters + digits.
        if (preg_match('/^([A-Z0-9]{12,13}|[A-Z0-9]{16})$/', $pin) !== 1) {
            throw new InvalidArgumentException('Invalid PIN, expected 12, 13 or 16 characters.');
        }

        return $pin;
    }
}
