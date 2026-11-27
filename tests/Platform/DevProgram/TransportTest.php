<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Logging\LogType;
use iswilson\CanadaPost\Platform\DevProgram\Transport;
use iswilson\CanadaPost\Tests\Support\Doubles\SpyLogger;
use iswilson\CanadaPost\Tests\Support\FixtureFile;
use iswilson\CanadaPost\Tests\Support\Traits\HttpMockHandlerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(Transport::class)]
#[Group('DevProgram')]
final class TransportTest extends TestCase
{
    use HttpMockHandlerTrait;

    #[DataProvider('getRequestPaths')]
    public function testRequestUriIsBuiltCorrectly(string $path, string $expectedUri): void
    {
        $request = $this->captureHttpTransaction('POST', $path)['request'];
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getRequestPaths(): iterable
    {
        yield 'with forward slash' => ['/rs/ship/price', 'https://localhost/rs/ship/price'];
        yield 'without forward slash' => ['rs/ship/price', 'https://localhost/rs/ship/price'];
    }

    public function testRequestAuthorizationHeaderIsPresent(): void
    {
        $request = $this->captureHttpTransaction('POST', 'test')['request'];
        self::assertSame('Basic ' . base64_encode('username:password'), $request->getHeaderLine('Authorization'));
    }

    public function testRequestMethodIsUppercase(): void
    {
        $request = $this->captureHttpTransaction('post', 'test')['request'];
        self::assertSame('POST', $request->getMethod());
    }

    public function testRequestHeadersArePresent(): void
    {
        $headers = [
            'Content-Type' => 'application/vnd.cpc.ship.rate-v4+xml',
            'Accept' => 'application/vnd.cpc.ship.rate-v4+xml',
        ];
        $request = $this->captureHttpTransaction('POST', 'test', $headers)['request'];
        foreach ($headers as $header => $value) {
            self::assertSame($value, $request->getHeaderLine($header));
        }
    }

    public function testRequestBodyIsPresent(): void
    {
        $request = $this->captureHttpTransaction('POST', 'test', body: 'test body')['request'];
        self::assertSame('test body', (string) $request->getBody());
    }

    public function testRequestWithoutBodyIsEmpty(): void
    {
        $request = $this->captureHttpTransaction('POST', 'test')['request'];
        self::assertSame('', (string) $request->getBody());
    }

    public function testDefaultRequestTimeout(): void
    {
        $requestOptions = $this->captureHttpTransaction('POST', 'test')['options'];
        self::assertSame(30.0, $requestOptions['timeout']);
    }

    #[DataProvider('getTimeoutValues')]
    public function testRequestTimeoutIsOverridden(float $timeout): void
    {
        $requestOptions = $this->captureHttpTransaction('POST', 'test', timeout: $timeout)['options'];
        self::assertSame($timeout, $requestOptions['timeout']);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function getTimeoutValues(): iterable
    {
        yield 'Zero seconds' => [0];
        yield 'Five seconds' => [5];
    }

    public function testNegativeRequestTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->captureHttpTransaction('POST', 'test', timeout: -5);
        $this->expectExceptionMessage('must be zero or greater');
    }

    public function testHttp200Response(): void
    {
        $xml = FixtureFile::load('DevProgram/get-rates.xml');
        $mockResponse = new Response(body: $xml);
        $response = $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse)['result'];
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($xml, (string) $response->getBody());
    }

    #[DataProvider('getHttp2xxResponseStatusCodes')]
    public function testHttp2xxResponse(int $statusCode): void
    {
        $mockResponse = new Response($statusCode);
        $response = $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse)['result'];
        self::assertSame($statusCode, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function getHttp2xxResponseStatusCodes(): iterable
    {
        /** @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/messagescodetables.jsf */
        yield '200 Success, process body.' => [200];
        yield '202 The requested resource is not yet available.' => [202];
        yield '204 Success, no action required.' => [204];
    }

    #[DataProvider('getHttp4xxAnd5xxResponseStatusCodes')]
    public function testHttp4xxAnd5xxResponse(int $statusCode): void
    {
        $xml = FixtureFile::load('DevProgram/error-multiple.xml');
        $mockResponse = new Response($statusCode, body: $xml);
        $response = $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse)['result'];
        self::assertSame($statusCode, $response->getStatusCode());
        self::assertSame($xml, (string) $response->getBody());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function getHttp4xxAnd5xxResponseStatusCodes(): iterable
    {
        /** @see https://www.canadapost-postescanada.ca/info/mc/business/productsservices/developers/messagescodetables.jsf */
        yield '400 Error' => [400];
        yield '404 Not Found' => [404];
        yield '500 Error' => [500];
    }

    public function testConnectException(): void
    {
        $mockResponse = new ConnectException('Connection refused', new Request('POST', 'https://localhost'));
        try {
            $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertNull($e->httpStatusCode);
            self::assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function testInvalidGuzzleArguments(): void
    {
        $this->expectException(\LogicException::class);
        $mockResponse = new InvalidArgumentException('Invalid argument');
        $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse);
    }

    public function testSendWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $this->captureHttpTransaction('POST', 'test', logger: $spyLogger);
        self::assertCount(2, $spyLogger->records());
        self::assertCount(1, $spyLogger->records(LogType::Request));
        self::assertCount(1, $spyLogger->records(LogType::Response));
    }

    #[DataProvider('getHttpStatusCodeLogLevels')]
    public function testResponseStatusCodeLogLevels(int $statusCode, string $expectedLevel): void
    {
        $spyLogger = new SpyLogger();
        $mockResponse = new Response($statusCode);
        $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse, logger: $spyLogger);
        self::assertSame($expectedLevel, $spyLogger->sole(LogType::Response)->level);
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

    public function testConnectExceptionWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mockResponse = new ConnectException('Connection refused', new Request('POST', 'https://localhost'));
        try {
            $this->captureHttpTransaction('POST', 'test', mockResponse: $mockResponse, logger: $spyLogger);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException) {
            self::assertCount(2, $spyLogger->records());
            self::assertSame(LogLevel::ERROR, $spyLogger->sole(LogType::Request)->level);
            self::assertSame(LogLevel::ERROR, $spyLogger->sole(LogType::Response)->level);
            self::assertStringContainsString('Connection refused', $spyLogger->sole(LogType::Response)->message);
        }
    }

    public function testBareHandlerWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler([new Response()]);
        $this->transport(guzzleOptions: ['handler' => $mock], logger: $spyLogger)->send('POST', 'test');
        self::assertCount(2, $spyLogger->records());
        self::assertCount(1, $spyLogger->records(LogType::Request));
        self::assertCount(1, $spyLogger->records(LogType::Response));
    }

    public function testReusedHandlerStackWithLogging(): void
    {
        $spyLogger = new SpyLogger();
        $mock = new MockHandler();
        $handlerStack = HandlerStack::create($mock);

        for ($i = 0; $i < 5; ++$i) {
            $mock->append(new Response());
            $this->transport(guzzleOptions: ['handler' => $handlerStack], logger: $spyLogger)->send('POST', 'test');
        }

        self::assertCount(0, $mock);
        self::assertCount(10, $spyLogger->records());
        self::assertCount(5, $spyLogger->records(LogType::Request));
        self::assertCount(5, $spyLogger->records(LogType::Response));
    }

    public function testHandlerStackIsNotChanged(): void
    {
        $spyLogger = new SpyLogger();
        $handlerStack = HandlerStack::create(new MockHandler([new Response()]));
        $this->transport(guzzleOptions: ['handler' => $handlerStack], logger: $spyLogger);

        // Send request with original handler.
        $client = new Client(['handler' => $handlerStack]);
        $client->request('GET', 'https://localhost');

        self::assertCount(0, $spyLogger->records());
    }

    public function testNoCredentialsAreLogged(): void
    {
        $spyLogger = new SpyLogger();
        $this->captureHttpTransaction('POST', 'test', logger: $spyLogger);

        self::assertCount(2, $spyLogger->records());

        foreach ($spyLogger->records() as $record) {
            self::assertStringNotContainsString('password', $record->message);
            self::assertStringNotContainsString(base64_encode('username:password'), $record->message);
        }

        if (str_contains($spyLogger->sole(LogType::Request)->message, 'Authorization:')) {
            self::assertStringContainsString('Authorization: [REDACTED]', $spyLogger->sole(LogType::Request)->message);
        }
    }

    public function testOriginalRequestIsUnmodifiedByLogging(): void
    {
        $spyLogger = new SpyLogger();
        $request = $this->captureHttpTransaction('POST', 'test', logger: $spyLogger)['request'];
        self::assertSame('Basic ' . base64_encode('username:password'), $request->getHeaderLine('Authorization'));
    }

    public function testRequestLogFormat(): void
    {
        $spyLogger = new SpyLogger();
        $this->captureHttpTransaction('POST', 'test', body: 'testing', logger: $spyLogger);
        $message = $spyLogger->sole(LogType::Request)->message;
        self::assertStringStartsWith(">>>>>>>> REQUEST - POST /test\n", $message);
        self::assertStringEndsWith(LogType::BODY_SEPARATOR . 'testing', $message);
    }

    public function testResponseLogFormat(): void
    {
        $mockResponse = new Response(body: 'testing');
        $spyLogger = new SpyLogger();
        $this->captureHttpTransaction('GET', 'test', mockResponse: $mockResponse, logger: $spyLogger);
        $message = $spyLogger->sole(LogType::Response)->message;
        self::assertStringStartsWith("<<<<<<<< RESPONSE 200 OK - GET /test\n", $message);
        self::assertStringContainsString(LogType::ERROR_SEPARATOR . 'NULL' . LogType::BODY_SEPARATOR, $message);
        self::assertStringEndsWith(LogType::BODY_SEPARATOR . 'testing', $message);
    }

    /**
     * @param array{handler?: callable|null} $guzzleOptions
     */
    private function transport(
        string $baseUrl = 'https://localhost',
        string $username = 'username',
        string $password = 'password',
        array $guzzleOptions = [],
        ?float $timeout = null,
        ?LoggerInterface $logger = null,
    ): Transport {
        return new Transport($baseUrl, $username, $password, $guzzleOptions, $timeout, $logger);
    }

    /**
     * @param array<string, non-empty-list<string>|string> $headers
     * @param array{handler?: callable|null} $guzzleOptions
     *
     * @return array{
     *     request: RequestInterface,
     *     response: ResponseInterface|null,
     *     error: mixed,
     *     options: array<string, mixed>,
     *     result: ResponseInterface,
     * }
     */
    private function captureHttpTransaction(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null,
        array $guzzleOptions = [],
        ?float $timeout = null,
        ResponseInterface|\Throwable $mockResponse = new Response(),
        ?LoggerInterface $logger = null,
    ): array {
        $historyContainer = [];
        $handlerStack = $this->handlerStack($historyContainer, [$mockResponse]);

        // Add key 'result' for response after it passed through send.
        $result = $this
            ->transport(guzzleOptions: ['handler' => $handlerStack] + $guzzleOptions, timeout: $timeout, logger: $logger)
            ->send($method, $path, $headers, $body);
        $firstTransaction = $historyContainer[0] ?? throw new \LogicException('No request sent.');
        $firstTransaction['result'] = $result;

        return $firstTransaction;
    }
}
