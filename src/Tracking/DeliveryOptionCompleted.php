<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tracking;

/**
 * Whether parcel delivery options were satisfied.
 */
enum DeliveryOptionCompleted: string
{
    /**
     * At least one option was not satisfied.
     */
    case NotSatisfied = '0';
    /**
     * All options were satisfied.
     */
    case Satisfied = '1';
    /**
     * No options were found.
     */
    case NoOptions = '2';
}
