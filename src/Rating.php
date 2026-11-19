<?php

declare(strict_types=1);

namespace iswilson\CanadaPost;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use LSS\Array2XML;

/**
 * Rating contains Canada Post API calls for package rates.
 *
 * @package CanadaPost
 *
 * @see https://www.canadapost.ca/cpo/mc/business/productsservices/developers/services/rating/default.jsf
 */
class Rating
{
    public function __construct(
        protected readonly Transport $transport,
        protected readonly string $customerNumber,
        protected readonly ?string $contractId = null,
    ) {}

    /**
     * Get the shipping rates for the given locations and weight.
     *
     * @param string $originPostalCode
     *   The origin postal code.
     * @param Destination $destination
     *   The destination postal code.
     * @param Parcel $parcel
     *   Parcel characteristics (weight, length, width, height).
     * @param array<string, mixed> $options
     *   The array of options. Rating specific options:
     *     - service_codes: https://www.canadapost.ca/cpo/mc/business/productsservices/developers/services/rating/getrates/default.jsf
     *     - options_codes: https://www.canadapost.ca/cpo/mc/business/productsservices/developers/services/rating/getrates/default.jsf
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
        array $options = [],
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

        // TODO split the options for Canada Post from the options for Guzzle.
        // They can either be separate variables or the Canada Post options can
        // be within a sub-array keyed by canada_post (or the other way around).
        $serviceCodes = $options['service_codes'] ?? [];
        if ($serviceCodes !== []) {
            $content['services']['service-code'] = $this->parseServiceCodes($options);
        }
        $optionCodes = $options['option_codes'] ?? [];
        if ($optionCodes !== []) {
            $content['options']['option'] = $this->parseOptionCodes($options);
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

        $responseBody = (string) $response->getBody();
        // Response is empty.
        if ($responseBody === '') {
            throw new CanadaPostException(
                'Canada Post API returned an empty response.',
                httpStatusCode: $response->getStatusCode(),
            );
        }

        $doc = self::loadXml($responseBody);
        // Response is not valid XML.
        if ($doc === null) {
            throw new CanadaPostException(
                'Canada Post API returned invalid XML.',
                httpStatusCode: $response->getStatusCode(),
            );
        }

        switch ($doc->documentElement?->localName) {
            case 'price-quotes':
                return self::parsePriceQuotes($doc);
            case 'messages':
                $errorMessages = self::parseErrorMessages($doc);
                $errorCodes = (count($errorMessages) > 0) ? array_column($errorMessages, 'code') : ['no error code'];

                throw new CanadaPostException(
                    sprintf('Canada Post API request failed (%s).', implode(', ', $errorCodes)),
                    errors: $errorMessages,
                    httpStatusCode: $response->getStatusCode(),
                );
        }

        // Response is valid XML but doesn't contain any elements we know how
        // to parse.
        throw new CanadaPostException(
            'Canada Post API returned valid XML but required elements are missing.',
            httpStatusCode: $response->getStatusCode(),
        );
    }

    private static function loadXml(string $xml): ?\DOMDocument
    {
        $doc = new \DOMDocument();

        // Suppress libxml errors while reading data.
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        return $loaded ? $doc : null;
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
     * Parse an XML response into an array of error codes + messages.
     *
     * @internal
     *
     * @return list<array{code: string, message: string}>
     */
    public static function parseErrorMessages(\DOMDocument $doc): array
    {
        $messages = [];
        // Extract error message data.
        $nodes = $doc->getElementsByTagName('message');
        foreach ($nodes as $node) {
            $messages[] = [
                'code' => $node->getElementsByTagName('code')->item(0)->textContent ?? '',
                'message' => $node->getElementsByTagName('description')->item(0)->textContent ?? '',
            ];
        }

        return $messages;
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
     * @param array<string, mixed> $options
     *   The options array.
     *
     * @return list<string>
     *   The list of services to look up.
     */
    protected function parseServiceCodes(array $options): array
    {
        $services = [];
        foreach ($options['service_codes'] as $serviceCode) {
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
     * @param array{option_codes: list<string>} $options
     *   The options array.
     *
     * @return list<array{option-code: string}>
     *   The list of options with the option-code.
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    protected function parseOptionCodes(array $options): array
    {
        $valid_options = [];
        foreach ($options['option_codes'] as $optionCode) {
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
