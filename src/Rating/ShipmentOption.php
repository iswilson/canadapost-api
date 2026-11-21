<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Rating;

use iswilson\CanadaPost\Exception\InvalidArgumentException;

/**
 * Defines a Canada Post shipment option.
 */
final readonly class ShipmentOption
{
    public const array CODES = [
        'SO'   => 'Signature',
        'COV'  => 'Coverage',
        'COD'  => 'Collect on delivery',
        'PA18' => 'Proof of Age Required - 18',
        'PA19' => 'Proof of Age Required - 19',
        'HFP'  => 'Card for pickup',
        'DNS'  => 'Do not safe drop',
        'LAD'  => 'Leave at door - do not card',
    ];

    public const array AMOUNT_REQUIRED = [
        'COV',
        'COD',
    ];

    /** Canada Post option code. */
    public string $code;

    public function __construct(
        string $code,
        /** COV/COD amount in cents (required for COV/COD). */
        public ?int $amountCents = null,
    ) {
        // Convert code to uppercase and remove leading/trailing whitespace.
        $this->code = strtoupper(trim($code));

        // Check if code is in list of valid codes.
        if (!array_key_exists($this->code, self::CODES)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported option code. Supported options are: %s.',
                implode(', ', array_keys(self::CODES)),
            ));
        }

        // Check if code is allowed to have an amount.
        if ($this->amountCents !== null && !in_array($this->code, self::AMOUNT_REQUIRED, true)) {
            throw new InvalidArgumentException(sprintf(
                'Amount is unsupported for option code: %s.',
                $this->code,
            ));
        }

        // Check if code requires an amount.
        if ($this->amountCents === null && in_array($this->code, self::AMOUNT_REQUIRED, true)) {
            throw new InvalidArgumentException(sprintf(
                'Amount is required for option code: %s.',
                $this->code,
            ));
        }

        // Check if amount is outside of min/max.
        if ($this->amountCents !== null && ($this->amountCents < 1 || $this->amountCents > 99999999)) {
            throw new InvalidArgumentException(sprintf(
                'Amount for "%s" option must be between 1 and 99,999,999 cents ($0.01 to $999,999.99).',
                $this->code,
            ));
        }
    }

    /**
     * Dedupe provided shipment option list.
     *
     * @param list<ShipmentOption> $options
     *
     * @return list<ShipmentOption>
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public static function unique(array $options): array
    {
        $uniqueOptions = [];

        foreach ($options as $option) {
            if (
                array_key_exists($option->code, $uniqueOptions)
                && $uniqueOptions[$option->code]->amountCents !== $option->amountCents
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Option "%s" appears twice with differing amounts.',
                    $option->code,
                ));
            }
            $uniqueOptions[$option->code] = $option;
        }

        return array_values($uniqueOptions);
    }
}
