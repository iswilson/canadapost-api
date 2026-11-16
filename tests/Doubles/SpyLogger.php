<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Doubles;

use Psr\Log\AbstractLogger;

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
