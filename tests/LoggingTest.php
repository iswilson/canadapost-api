<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\CanadaPost;
use iswilson\CanadaPost\ClientBase;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Platform\DevProgram\DevProgram;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use iswilson\CanadaPost\Rating\PriceQuote;
use iswilson\CanadaPost\Tests\Doubles\SpyLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(CanadaPost::class)]
#[CoversClass(ClientBase::class)]
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
        self::assertCount(4, $price_quotes);
    }

    public function testTrackingWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/tracking-summary-success.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        CanadaPost::devProgram(
            'username',
            'password',
            '123456789',
            logger: $spyLogger,
        )->tracking()->getSummary(
            '1234567890123456',
            options: ['handler' => $handlerStack],
        );

        self::assertCount(1, $spyLogger->records);
    }

    public function testSharedLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler();
        $handlerStack = HandlerStack::create($mock);
        $platform = CanadaPost::devProgram('username', 'password', '123456789', logger: $spyLogger);

        $mock->append(new Response(200, [], file_get_contents(__DIR__ . '/Mocks/tracking-summary-success.xml')));
        $platform->tracking()->getSummary(
            '1234567890123456',
            options: ['handler' => $handlerStack],
        );

        $mock->append(new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')));
        $platform->rating()->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            ['handler' => $handlerStack],
        );

        self::assertCount(2, $spyLogger->records);
    }

    public function testReusedHandlerStack(): void
    {
        $spyLogger = new SpyLogger();
        $xml = file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml');
        $mock = new MockHandler();
        $handlerStack = HandlerStack::create($mock);

        for ($i = 0; $i < 5; ++$i) {
            $mock->append(new Response(200, [], $xml));

            CanadaPost::devProgram(
                'username',
                'password',
                '123456789',
                logger: $spyLogger,
            )->rating()->getRates(
                'H0H0H0',
                new Destination('CA', 'H0H0H0'),
                new Parcel(500),
                ['handler' => $handlerStack],
            );
        }

        self::assertCount(0, $mock);
        self::assertCount(5, $spyLogger->records);
    }

    public function testBareHandler(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml')),
        ]);

        $price_quotes = CanadaPost::devProgram(
            'username',
            'password',
            '123456789',
            logger: $spyLogger,
        )->rating()->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            ['handler' => $mock],
        );

        self::assertCount(4, $price_quotes);
        self::assertCount(1, $spyLogger->records);
    }

    #[DataProvider('getHttpStatusCodeLogLevels')]
    public function testLogLevels(int $statusCode, string $expectedLevel): void
    {
        $spyLogger = new SpyLogger();
        try {
            $this->getRatingResponse($spyLogger, $statusCode);
        } catch (CanadaPostException) {
        }
        self::assertCount(1, $spyLogger->records);
        self::assertSame($expectedLevel, $spyLogger->records[0]['level']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function getHttpStatusCodeLogLevels(): iterable
    {
        yield '200 OK' => [200, LogLevel::DEBUG];
        yield '400 Bad Request' => [400, LogLevel::ERROR];
        yield '500 Internal Server Error' => [500, LogLevel::ERROR];
    }

    public function testConnectException(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'http://localhost')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        try {
            CanadaPost::devProgram(
                'username',
                'password',
                '123456789',
                logger: $spyLogger,
            )->rating()->getRates(
                'H0H0H0',
                new Destination('CA', 'H0H0H0'),
                new Parcel(500),
                ['handler' => $handlerStack],
            );
        } catch (CanadaPostException) {
        }

        self::assertCount(1, $spyLogger->records);
        self::assertSame(LogLevel::ERROR, $spyLogger->records[0]['level']);
        self::assertStringEndsWith('NULL', $spyLogger->records[0]['message']);

    }

    /**
     * @return list<PriceQuote>
     */
    private function getRatingResponse(?LoggerInterface $logger = null, int $statusCode = 200): array
    {
        $xml = match ($statusCode) {
            200 => file_get_contents(__DIR__ . '/Mocks/rating-response-success.xml'),
            400 => file_get_contents(__DIR__ . '/Mocks/rating-response-error.xml'),
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
        )->rating()->getRates(
            'H0H0H0',
            new Destination('CA', 'H0H0H0'),
            new Parcel(500),
            ['handler' => $handlerStack],
        );
    }
}
