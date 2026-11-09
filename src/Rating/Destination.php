<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;

/**
 * Defines a Canada Post parcel destination.
 */
final readonly class Destination
{
    /** An ISO 3166-1 alpha-2 country code. */
    public string $countryCode;

    /** A postal or zip code. Optional for international destinations. */
    public ?string $postalCode;

    /**
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function __construct(string $countryCode, ?string $postalCode = null)
    {
        $this->countryCode = self::normalizeCountryCode($countryCode);

        // Normalize postal/zip code based on destination.
        if ($postalCode !== null) {
            $this->postalCode = match ($this->countryCode) {
                'CA' => self::normalizeCanadianPostalCode($postalCode),
                'US' => self::normalizeZipCode($postalCode),
                default => self::normalizeInternationalPostalCode($postalCode),
            };
        } elseif (in_array($this->countryCode, ['CA', 'US'], true)) {
            // Required if destination is in Canada or US.
            throw new InvalidArgumentException('Missing required parameter $postalCode.');
        } else {
            // Optional if international.
            $this->postalCode = null;
        }
    }

    /**
     * Normalize ISO 3166-1 alpha-2 country code.
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    private static function normalizeCountryCode(string $countryCode): string
    {
        // Remove leading/trailing whitespace + convert to uppercase.
        $code = strtoupper(trim($countryCode));

        // Must be 2 uppercase characters.
        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid country code: %s.', $countryCode));
        }

        return $code;
    }

    /**
     * Normalize and validate Canadian postal code.
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public static function normalizeCanadianPostalCode(string $postalCode): string
    {
        // Convert to uppercase and remove invalid characters.
        $code = (string) preg_replace('/[^A-Z0-9]+/', '', strtoupper($postalCode));

        // Must be 3 sets of alternating letter + digit (e.g. H0H0H0).
        if (preg_match('/^[A-Z][0-9][A-Z][0-9][A-Z][0-9]$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid postal code: %s.', $postalCode));
        }

        return $code;
    }

    /**
     * Normalize and validate US zip code.
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    private static function normalizeZipCode(string $zipCode): string
    {
        // Remove non-digits.
        $code = (string) preg_replace('/[^0-9]+/', '', $zipCode);

        // Add dash if longer than 5 digits.
        if (strlen($code) > 5) {
            $code = substr_replace($code, '-', 5, 0);
        }

        // Must be either 5 digits or 5-4 (extended zip code).
        if (preg_match('/^[0-9]{5}(-[0-9]{4})?$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid zip code: %s.', $zipCode));
        }

        return $code;
    }

    /**
     * Normalize and validate international postal code.
     *
     * Since this field is optional, null is returned instead of an exception if
     * validation fails.
     */
    private static function normalizeInternationalPostalCode(string $postalCode): ?string
    {
        // Remove leading/trailing whitespace.
        $code = trim($postalCode);

        // Should be between 1 and 10 characters.
        if (preg_match('/^.{1,10}$/', $code) === 1) {
            return $code;
        }

        return null;
    }
}
