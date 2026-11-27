<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Support;

final readonly class LogRecord
{
    /**
     * @param array<mixed> $context
     */
    public function __construct(
        public mixed $level,
        public string $message,
        public array $context = [],
    ) {}
}
