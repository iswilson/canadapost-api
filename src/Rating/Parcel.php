<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;

/**
 * Defines a Canada Post parcel.
 */
final readonly class Parcel
{
    /** Maximum parcel weight (grams). */
    private const int MAX_WEIGHT_GRAMS = 99_999;

    /** Maximum parcel length/width/height (millimeters). */
    private const int MAX_DIMENSION_MM = 999_9;

    /** Weight (grams). */
    public int $weightGrams;

    /** Longest dimension (millimeters). */
    public ?int $lengthMm;

    /** Second-longest dimension (millimeters). */
    public ?int $widthMm;

    /** Shortest dimension (millimeters). */
    public ?int $heightMm;

    /**
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function __construct(
        int $weightGrams,
        ?int $lengthMm = null,
        ?int $widthMm = null,
        ?int $heightMm = null,
    ) {
        // Weight must be between min/max.
        if ($weightGrams < 1 || $weightGrams > self::MAX_WEIGHT_GRAMS) {
            throw new InvalidArgumentException(vsprintf('Weight must be between 1 and %d grams, got: %d.', [
                self::MAX_WEIGHT_GRAMS,
                $weightGrams,
            ]));
        }
        $this->weightGrams = $weightGrams;

        // No dimensions were supplied.
        if ($lengthMm === null && $widthMm === null && $heightMm === null) {
            $this->lengthMm = null;
            $this->widthMm = null;
            $this->heightMm = null;
            return;
        }

        // At least one dimension was supplied.
        $dimensions = [
            'length' => $lengthMm,
            'width' => $widthMm,
            'height' => $heightMm,
        ];

        // Check for missing dimensions.
        $missing = [];
        foreach ($dimensions as $key => $value) {
            if ($value === null) {
                $missing[] = $key;
            }
        }
        if (count($missing) > 0) {
            throw new InvalidArgumentException(sprintf('Either all or no dimensions must be supplied, missing: %s.', implode(', ', $missing)));
        }

        // Each dimension must be between min/max.
        foreach ($dimensions as $key => $value) {
            if ($value < 1 || $value > self::MAX_DIMENSION_MM) {
                throw new InvalidArgumentException(vsprintf('%s must be between 1 and %d millimeters, got: %d.', [
                    ucfirst($key),
                    self::MAX_DIMENSION_MM,
                    $value,
                ]));
            }
        }

        // Sort in descending order before assigning (keys no longer needed).
        rsort($dimensions);
        $this->lengthMm = $dimensions[0];
        $this->widthMm = $dimensions[1];
        $this->heightMm = $dimensions[2];
    }

    /**
     * Report whether this parcel has dimensions set.
     */
    public function hasDimensions(): bool
    {
        return $this->lengthMm !== null && $this->widthMm !== null && $this->heightMm !== null;
    }
}
