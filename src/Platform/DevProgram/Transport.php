<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\MessageFormatterInterface;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Logging\ContextLogger;
use iswilson\CanadaPost\Logging\LogType;
use iswilson\CanadaPost\Logging\RedactingMessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * HTTP transport for Canada Post Developer Program (XML with Basic auth).
 */
final readonly class Transport
{
    public const float DEFAULT_TIMEOUT = 30;
    private ClientInterface $client;

    /**
     * @param array{handler?: callable|null} $guzzleOptions
     *   Guzzle client options {@see https://docs.guzzlephp.org/en/stable/request-options.html}.
     */
    public function __construct(
        string $baseUrl,
        string $username,
        #[\SensitiveParameter]
        string $password,
        array $guzzleOptions = [],
        ?float $timeout = null,
        private ?LoggerInterface $logger = null,
    ) {
        if ($timeout !== null && $timeout < 0) {
            throw new InvalidArgumentException('Timeout must be zero or greater.');
        }

        $handlerStack = $this->buildHandlerStack($guzzleOptions['handler'] ?? null);
        $this->client = new GuzzleClient([
            ...$guzzleOptions,
            'handler' => $handlerStack,
            'base_uri' => $baseUrl,
            'auth' => [$username, $password],
            'timeout' => $timeout ?? self::DEFAULT_TIMEOUT,
        ]);
    }

    /**
     * Create Guzzle handler stack with logging middleware.
     *
     * @return HandlerStack<callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>>
     */
    private function buildHandlerStack(?callable $handler): HandlerStack
    {
        // Clone passed handler to prevent adding multiple copies of middleware.
        $handlerStack = ($handler instanceof HandlerStack)
            ? clone $handler
            : HandlerStack::create($handler);

        // Log middleware goes on bottom of stack so that HTTP 4xx/5xx are
        // logged as errors.
        if ($this->logger !== null) {
            $handlerStack->unshift(Middleware::log(
                new ContextLogger($this->logger, [LogType::CONTEXT_KEY => LogType::Request->value]),
                self::logFormatter(">>>>>>>> REQUEST - {method} {target}\n{req_headers}" . LogType::BODY_SEPARATOR . '{req_body}'),
                LogLevel::DEBUG,
            ), 'canadapost_api_log_request');

            $handlerStack->unshift(Middleware::log(
                new ContextLogger($this->logger, [LogType::CONTEXT_KEY => LogType::Response->value]),
                self::logFormatter("<<<<<<<< RESPONSE {code} {phrase} - {method} {target}\n{res_headers}" . LogType::ERROR_SEPARATOR . '{error}' . LogType::BODY_SEPARATOR . '{res_body}'),
                LogLevel::DEBUG,
            ), 'canadapost_api_log_response');
        }

        return $handlerStack;
    }

    /**
     * Build and send HTTP request.
     *
     * @param array<string, non-empty-list<string>|string> $headers
     *   HTTP headers.
     */
    public function send(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null,
    ): ResponseInterface {
        try {
            $options = [
                'headers' => $headers,
                'body' => $body,
            ];
            $response = $this->client->request(strtoupper($method), $path, $options);
        } catch (BadResponseException $e) {
            // HTTP 4xx and 5xx errors.
            return $e->getResponse();
        } catch (TransferException $e) {
            // Anything else transfer related: timeouts, DNS, refused.
            throw new CanadaPostException($e->getMessage(), previous: $e);
        } catch (GuzzleException $e) {
            // Should only be InvalidArgumentException.
            throw new \LogicException('Invalid arguments passed to HTTP client.', previous: $e);
        }

        return $response;
    }

    /**
     * Build message formatter wrapped with redacting message formatter.
     *
     * @see MessageFormatter for variable substitutions.
     */
    private static function logFormatter(string $format): MessageFormatterInterface
    {
        return new RedactingMessageFormatter(new MessageFormatter($format));
    }
}
