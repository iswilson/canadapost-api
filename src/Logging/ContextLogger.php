<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Add context to log messages.
 *
 * @internal
 */
final class ContextLogger extends AbstractLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $context = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $this->context + $context);
    }
}
