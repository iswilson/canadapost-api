<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\ServiceInfo;

/**
 * Defines a service information message.
 */
final readonly class InfoMessage
{
    public function __construct(
        /** Text of the message. */
        public string $messageText,
        /** Start date and time of the outage. */
        public \DateTimeImmutable $fromDateTime,
        /** Expected end date and time for the scheduled outage. */
        public \DateTimeImmutable $toDateTime,
    ) {}
}
