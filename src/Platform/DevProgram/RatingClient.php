<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use iswilson\CanadaPost\Rating\RatingClientInterface;
use LSS\Array2XML;

/**
 * Rating contains Canada Post API calls for package rates.
 */
final readonly class RatingClient implements RatingClientInterface
{
    public function __construct(
        private Transport $transport,
        private string $customerNumber,
        private ?string $contractId = null,
    ) {}

    public function getRates(
        string $originPostalCode,
        Destination $destination,
        Parcel $parcel,
        array $optionCodes = [],
        array $serviceCodes = [],
    ): array {
        $content = [
            'customer-number' => $this->customerNumber,
            'origin-postal-code' => Destination::normalizeCanadianPostalCode($originPostalCode),
        ];

        // Destination.
        switch ($destination->countryCode) {
            case 'CA':
                $content['destination']['domestic']['postal-code'] = $destination->postalCode ?? throw new \LogicException('Missing postal code.');
                break;
            case 'US':
                $content['destination']['united-states']['zip-code'] = $destination->postalCode ?? throw new \LogicException('Missing postal code.');
                break;
            default:
                $content['destination']['international']['country-code'] = $destination->countryCode;
                if ($destination->postalCode !== null) {
                    $content['destination']['international']['postal-code'] = $destination->postalCode;
                }
        }

        // Convert weight to kilograms.
        $content['parcel-characteristics']['weight'] = self::formatDecimal($parcel->weightGrams, 3);
        if ($parcel->hasDimensions()) {
            // Convert dimensions to centimeters.
            $content['parcel-characteristics']['dimensions'] = [
                'length' => self::formatDecimal($parcel->lengthMm, 1),
                'width' => self::formatDecimal($parcel->widthMm, 1),
                'height' => self::formatDecimal($parcel->heightMm, 1),
            ];
        }

        if ($serviceCodes !== []) {
            $content['services']['service-code'] = $this->parseServiceCodes($serviceCodes);
        }
        if ($optionCodes !== []) {
            $content['options']['option'] = $this->parseOptionCodes($optionCodes);
        }

        if ($this->contractId !== null) {
            $content['contract-id'] = $this->contractId;
        }

        $xml = Array2XML::createXML('mailing-scenario', $content);
        $envelope = $xml->documentElement;
        $envelope->setAttribute(
            'xmlns',
            'http://www.canadapost.ca/ws/ship/rate-v4',
        );
        $payload = $xml->saveXML();

        $response = $this->transport->send(
            'POST',
            "rs/ship/price",
            [
                'Content-Type' => 'application/vnd.cpc.ship.rate-v4+xml',
                'Accept' => 'application/vnd.cpc.ship.rate-v4+xml',
            ],
            $payload,
        );

        $doc = XmlResponse::toDOMDocument($response, 'price-quotes');

        return self::parsePriceQuotes($doc);
    }

    /**
     * Parse an XML response into an array of PriceQuote.
     *
     * @internal
     *
     * @return list<PriceQuote>
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     */
    public static function parsePriceQuotes(\DOMDocument $doc): array
    {
        $priceQuotes = [];
        // Extract price quote data.
        $nodes = $doc->getElementsByTagName('price-quote');
        foreach ($nodes as $node) {
            $serviceCode = $node->getElementsByTagName('service-code')->item(0)->textContent ?? '';
            if ($serviceCode === '') {
                throw new CanadaPostException('Canada Post API returned an empty service code.');
            }

            $serviceName = $node->getElementsByTagName('service-name')->item(0)->textContent ?? '';
            if ($serviceName === '') {
                throw new CanadaPostException('Canada Post API returned an empty service name.');
            }

            $totalDue = $node->getElementsByTagName('due')->item(0)->textContent ?? '';

            // Missing (not empty) tax elements should have a default of zero.
            $gst = $node->getElementsByTagName('gst')->item(0)->textContent ?? '0';
            $pst = $node->getElementsByTagName('pst')->item(0)->textContent ?? '0';
            $hst = $node->getElementsByTagName('hst')->item(0)->textContent ?? '0';

            $priceQuotes[] = new PriceQuote(
                $serviceCode,
                $serviceName,
                self::convertDollarsToCents($totalDue),
                self::convertDollarsToCents($gst),
                self::convertDollarsToCents($pst),
                self::convertDollarsToCents($hst),
            );
        }

        return $priceQuotes;
    }

    /**
     * Get the Canada Post specific service codes,
     *
     * @return array<string, string>
     *   The array of service codes.
     *
     * @see https://www.canadapost.ca/cpo/mc/business/productsservices/developers/services/rating/getrates/default.jsf
     */
    public static function getServiceCodes(): array
    {
        return [
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
    }

    /**
     * Helper function to extract the service codes.
     *
     * @param list<string> $serviceCodes
     *   List of service codes.
     *
     * @return list<string>
     *   The list of services to look up.
     */
    protected function parseServiceCodes(array $serviceCodes): array
    {
        $services = [];
        foreach ($serviceCodes as $serviceCode) {
            if (!array_key_exists(strtoupper($serviceCode), self::getServiceCodes())) {
                $message = sprintf(
                    'Unsupported service code. Supported services are: %s',
                    implode(', ', array_keys(self::getServiceCodes())),
                );
                throw new InvalidArgumentException($message);
            }
            $services[] = strtoupper($serviceCode);
        }

        return $services;
    }

    /**
     * Format number as a decimal string without converting to float.
     *
     * @internal
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public static function formatDecimal(int $value, int $decimalPlaces): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('Value must not be negative, got: %d.', $value));
        }
        if ($decimalPlaces < 1) {
            throw new InvalidArgumentException(sprintf('Decimal places must be greater than 0, got: %d.', $decimalPlaces));
        }

        // Pad with zeroes until longer than decimal places.
        $number = str_pad((string) $value, $decimalPlaces + 1, '0', STR_PAD_LEFT);

        // Insert decimal character.
        return substr_replace($number, '.', -$decimalPlaces, 0);
    }

    /**
     * Convert dollar amount to cents, without floats.
     *
     * @internal
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     */
    public static function convertDollarsToCents(string $value): int
    {
        // Value must be a dollar amount (with or without a decimal).
        $value = trim($value);
        if (preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $value) !== 1) {
            throw new CanadaPostException('Canada Post API returned an invalid dollar amount.');
        }

        // Split into dollars and cents.
        [$dollars, $cents] = array_pad(explode('.', $value), 2, null);

        // Ensure cents are 2 digits long and stitch back together.
        $cents = str_pad((string) $cents, 2, '0');
        return (int) ($dollars . $cents);
    }

    /**
     * Get the Canada Post-specific option codes.
     *
     * @return array<string, string>
     *   The array of option codes.
     *
     * @see https://www.canadapost.ca/cpo/mc/business/productsservices/developers/services/rating/getrates/default.jsf
     */
    public static function getOptionCodes(): array
    {
        return [
            'SO' => 'Signature (SO)',
            'PA18' => 'Proof of Age Required - 18 (PA18)',
            'PA19' => 'Proof of Age Required - 19 (PA19)',
            'HFP' => 'Card for pickup (HFP)',
            'DNS' => 'Do not safe drop (DNS)',
            'LAD' => 'Leave at door - do not card (LAD)',
        ];
    }

    /**
     * Helper function to extract the option codes.
     *
     * @param list<string> $optionCodes
     *   List of option codes.
     *
     * @return list<array{option-code: string}>
     *   The list of options with the option-code.
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    protected function parseOptionCodes(array $optionCodes): array
    {
        $valid_options = [];
        foreach ($optionCodes as $optionCode) {
            if (!array_key_exists(strtoupper($optionCode), self::getOptionCodes())) {
                $message = sprintf(
                    'Unsupported option code. Supported options are: %s',
                    implode(', ', array_keys(self::getOptionCodes())),
                );
                throw new InvalidArgumentException($message);
            }
            // @todo Perhaps we should check for conflicts here, might be overkill.
            // From Canada Post docs:
            // There are some options that can be applied to a shipment that
            // conflict with the presence of another option. You can use the
            // "Get Option" call in advance to check the contents of the
            // <conflicting-options> group from a Get Option call for options
            // selected by end users or options available for a given service.
            $valid_options[] = [
                'option-code' => strtoupper($optionCode),
            ];
        }

        return $valid_options;
    }
}
