<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Support\Doubles;

use iswilson\CanadaPost\Logging\LogType;
use iswilson\CanadaPost\Tests\Support\LogRecord;
use Psr\Log\AbstractLogger;

final class SpyLogger extends AbstractLogger
{
    /** @var list<LogRecord> */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = new LogRecord($level, (string) $message, $context);
    }

    /**
     * @return list<LogRecord>
     */
    public function records(?LogType $type = null): array
    {
        if ($type !== null) {
            return array_values(array_filter(
                $this->records,
                function (LogRecord $record) use ($type): bool {
                    return ($record->context[LogType::CONTEXT_KEY] ?? null) === $type->value;
                },
            ));
        }

        return $this->records;
    }

    public function sole(?LogType $type = null): LogRecord
    {
        $records = $this->records(type: $type);
        if (count($records) !== 1) {
            throw new \UnexpectedValueException(sprintf(
                'Expected exactly one log record of type "%s", got %d.',
                $type->value ?? 'any',
                count($records),
            ));
        }

        return $records[0];
    }
}
