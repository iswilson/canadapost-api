<?php

declare(strict_types=1);

namespace iswilson\CanadaPost;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
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
class Rating extends ClientBase
{
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

        if (isset($this->config['contract_id'])) {
            $content['contract-id'] = $this->config['contract_id'];
        }

        $xml = Array2XML::createXML('mailing-scenario', $content);
        $envelope = $xml->documentElement;
        $envelope->setAttribute(
            'xmlns',
            'http://www.canadapost.ca/ws/ship/rate-v4',
        );
        $payload = $xml->saveXML();

        $options['raw_response'] = true;
        try {
            $response = (string) $this->post(
                "rs/ship/price",
                [
                    'Content-Type' => 'application/vnd.cpc.ship.rate-v4+xml',
                    'Accept' => 'application/vnd.cpc.ship.rate-v4+xml',
                ],
                $payload,
                $options,
            );
        } catch (GuzzleException $e) {
            throw new CanadaPostException(
                'Canada Post API request failed.',
                httpStatusCode: $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null,
                previous: $e,
            );
        }

        return self::parsePriceQuotes($response);
    }

    /**
     * Parse an XML response string into an array of PriceQuote.
     *
     * @internal
     *
     * @return list<PriceQuote>
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     */
    public static function parsePriceQuotes(string $response): array
    {
        // Response is empty.
        if ($response === '') {
            throw new CanadaPostException('Canada Post API returned an empty response.');
        }

        $doc = new \DOMDocument();

        // Suppress libxml errors while reading response data.
        $previousSetting = libxml_use_internal_errors(true);
        $responseLoaded = $doc->loadXML($response);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        // Response is not valid XML.
        if (!$responseLoaded) {
            throw new CanadaPostException('Canada Post API returned invalid XML.');
        }

        // Response is valid XML but doesn't contain price-quotes element.
        if ($doc->documentElement?->localName !== 'price-quotes') {
            throw new CanadaPostException('Canada Post API returned valid XML but <price-quotes> element is missing.');
        }

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
    public static function getServiceCodes()
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
                    'Unsupported service code: "%s". Supported services are %s',
                    $serviceCode,
                    implode(', ', array_keys(self::getServiceCodes())),
                );
                throw new InvalidArgumentException($message);
            }
            $services[] = $serviceCode;
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
            throw new CanadaPostException(sprintf('Canada Post API returned an invalid dollar amount, got: %s.', $value));
        }

        // Split into dollars and cents.
        [$dollars, $cents] = array_pad(explode('.', $value), 2, null);

        // Ensure cents are 2 digits long and stitch back together.
        $cents = str_pad((string) $cents, 2, '0');
        return (int) ($dollars . $cents);
    }
}
