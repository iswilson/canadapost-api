<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tracking;

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
