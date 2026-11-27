<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Logging;

use iswilson\CanadaPost\Logging\ContextLogger;
use iswilson\CanadaPost\Tests\Support\Doubles\SpyLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(ContextLogger::class)]
#[Group('Logging')]
final class ContextLoggerTest extends TestCase
{
    public function testContextIsAdded(): void
    {
        $spyLogger = new SpyLogger();
        (new ContextLogger($spyLogger, ['test_label' => 'test value']))->info('test message');

        self::assertSame(['test_label' => 'test value'], $spyLogger->sole()->context);
    }

    public function testContextFromCallerIsKept(): void
    {
        $spyLogger = new SpyLogger();
        (new ContextLogger($spyLogger, ['test_label' => 'test value']))
            ->info('test message', ['other_test_label' => 'other test value']);

        self::assertSame([
            'test_label' => 'test value',
            'other_test_label' => 'other test value',
        ], $spyLogger->sole()->context);
    }

    public function testContextIsNotOverwrittenByCaller(): void
    {
        $spyLogger = new SpyLogger();
        (new ContextLogger($spyLogger, ['test_label' => 'test value']))
            ->info('test message', ['test_label' => 'other test value']);

        self::assertSame(['test_label' => 'test value'], $spyLogger->sole()->context);
    }

    public function testLevelAndMessageAreUnaltered(): void
    {
        $spyLogger = new SpyLogger();
        (new ContextLogger($spyLogger))->log(LogLevel::DEBUG, 'test message');

        $record = $spyLogger->sole();
        self::assertSame(LogLevel::DEBUG, $record->level);
        self::assertSame('test message', $record->message);
    }
}
