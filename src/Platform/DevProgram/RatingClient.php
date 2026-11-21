<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use iswilson\CanadaPost\Rating\RatingClientInterface;

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
        $payload = $this->buildRequestXml(
            Destination::normalizeCanadianPostalCode($originPostalCode),
            $destination,
            $parcel,
            $this->parseOptionCodes($optionCodes),
            $this->parseServiceCodes($serviceCodes),
        );

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
     * @param list<string> $optionCodes
     * @param list<string> $serviceCodes
     *
     * @throws \DOMException
     */
    private function buildRequestXml(
        string $originPostalCode,
        Destination $destination,
        Parcel $parcel,
        array $optionCodes,
        array $serviceCodes,
    ): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');

        // Mailing scenario, the namespace will be added at the end.
        $root = $doc->appendChild(
            $doc->createElement('mailing-scenario'),
        );

        // Customer number.
        $root->appendChild(
            $doc->createElement('customer-number'),
        )->textContent = $this->customerNumber;

        // Contract ID if provided.
        if ($this->contractId !== null) {
            $root->appendChild(
                $doc->createElement('contract-id'),
            )->textContent = $this->contractId;
        }

        // Options.
        if ($optionCodes !== []) {
            $options = $root->appendChild(
                $doc->createElement('options'),
            );

            // Option.
            foreach ($optionCodes as $optionCode) {
                $option = $options->appendChild(
                    $doc->createElement('option'),
                );

                // Option code.
                $option->appendChild(
                    $doc->createElement('option-code'),
                )->textContent = $optionCode;
            }
        }

        // Parcel.
        $parcelCharacteristics = $root->appendChild(
            $doc->createElement('parcel-characteristics'),
        );
        // Parcel weight, converted to kilograms.
        $parcelCharacteristics->appendChild(
            $doc->createElement('weight'),
        )->textContent = self::formatDecimal($parcel->weightGrams, 3);
        // Parcel dimensions if provided, converted to centimeters.
        if ($parcel->hasDimensions()) {
            $dimensions = $parcelCharacteristics->appendChild(
                $doc->createElement('dimensions'),
            );

            // Length.
            $dimensions->appendChild(
                $doc->createElement('length'),
            )->textContent = self::formatDecimal($parcel->lengthMm, 1);
            // Width.
            $dimensions->appendChild(
                $doc->createElement('width'),
            )->textContent = self::formatDecimal($parcel->widthMm, 1);
            // Height.
            $dimensions->appendChild(
                $doc->createElement('height'),
            )->textContent = self::formatDecimal($parcel->heightMm, 1);
        }

        // Services.
        if ($serviceCodes !== []) {
            $services = $root->appendChild(
                $doc->createElement('services'),
            );

            // Service.
            foreach ($serviceCodes as $serviceCode) {
                // Service code.
                $services->appendChild(
                    $doc->createElement('service-code'),
                )->textContent = $serviceCode;
            }
        }

        // Origin postal code.
        $root->appendChild(
            $doc->createElement('origin-postal-code'),
        )->textContent = $originPostalCode;

        // Destination.
        $destinationElement = $root->appendChild(
            $doc->createElement('destination'),
        );
        // Destination type (Canada / US / International).
        switch ($destination->countryCode) {
            case 'CA':
                $type = $destinationElement->appendChild(
                    $doc->createElement('domestic'),
                );
                // Postal code required.
                $type->appendChild(
                    $doc->createElement('postal-code'),
                )->textContent = $destination->postalCode ?? throw new \LogicException('Missing postal code.');
                break;

            case 'US':
                $type = $destinationElement->appendChild(
                    $doc->createElement('united-states'),
                );
                // Zip code required.
                $type->appendChild(
                    $doc->createElement('zip-code'),
                )->textContent = $destination->postalCode ?? throw new \LogicException('Missing zip code.');
                break;

            default:
                $type = $destinationElement->appendChild(
                    $doc->createElement('international'),
                );
                $type->appendChild(
                    $doc->createElement('country-code'),
                )->textContent = $destination->countryCode;
                // Optional postal code.
                if ($destination->postalCode !== null) {
                    $type->appendChild(
                        $doc->createElement('postal-code'),
                    )->textContent = $destination->postalCode;
                }
        }

        // Children will go into this namespace as well.
        $root->setAttribute('xmlns', 'http://www.canadapost.ca/ws/ship/rate-v4');

        return $doc->saveXML();
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
     * @return list<string>
     *   List of options.
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
            $valid_options[] = strtoupper($optionCode);
        }

        return $valid_options;
    }
}
