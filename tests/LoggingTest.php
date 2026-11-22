<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\CanadaPost;
use iswilson\CanadaPost\Platform\DevProgram\DevProgram;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use iswilson\CanadaPost\Tests\Doubles\SpyLogger;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(CanadaPost::class)]
#[CoversClass(DevProgram::class)]
class LoggingTest extends TestCase
{
    public function testRatingWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $this->getRatingResponse($spyLogger);
        self::assertCount(1, $spyLogger->records);
    }

    public function testRatingWithoutLogging(): void
    {
        $price_quotes = $this->getRatingResponse();
        self::assertCount(2, $price_quotes);
    }

    public function testTrackingWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler([
            new Response(200, [], FixtureFile::load('DevProgram/get-tracking-summary.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        CanadaPost::devProgram(
            'username',
            'password',
            '123456789',
            logger: $spyLogger,
            handler: $handlerStack,
        )->tracking()->getSummary(
            '1234567890123456',
        );

        self::assertCount(1, $spyLogger->records);
    }

    public function testSharedLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler();
        $handlerStack = HandlerStack::create($mock);
        $platform = CanadaPost::devProgram('username', 'password', '123456789', logger: $spyLogger, handler: $handlerStack);

        $mock->append(new Response(200, [], FixtureFile::load('DevProgram/get-tracking-summary.xml')));
        $platform->tracking()->getSummary(
            '1234567890123456',
        );

        $mock->append(new Response(200, [], FixtureFile::load('DevProgram/get-rates.xml')));
        $platform->rating()->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
        );

        self::assertCount(2, $spyLogger->records);
    }

    /**
     * @return list<PriceQuote>
     */
    private function getRatingResponse(?LoggerInterface $logger = null, int $statusCode = 200): array
    {
        $xml = match ($statusCode) {
            200 => FixtureFile::load('DevProgram/get-rates.xml'),
            400 => FixtureFile::load('DevProgram/error-multiple.xml'),
            default => '',
        };

        $mock = new MockHandler([
            new Response($statusCode, [], $xml),
        ]);
        $handlerStack = HandlerStack::create($mock);

        return CanadaPost::devProgram(
            'username',
            'password',
            '123456789',
            logger: $logger,
            handler: $handlerStack,
        )->rating()->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
        );
    }
}
