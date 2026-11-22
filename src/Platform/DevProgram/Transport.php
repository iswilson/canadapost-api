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
use GuzzleHttp\Middleware;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Logging\RedactingMessageFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * HTTP transport for Canada Post Developer Program (XML with Basic auth).
 */
final readonly class Transport
{
    public const float DEFAULT_TIMEOUT = 30;
    /** @see MessageFormatter for variable substitutions. */
    private const string LOG_FORMAT = MessageFormatter::DEBUG;
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
            'handler' => $handlerStack,
            'base_uri' => $baseUrl,
            'auth' => [$username, $password],
            'timeout' => $timeout ?? self::DEFAULT_TIMEOUT,
        ] + $guzzleOptions);
    }

    /**
     * Create Guzzle handler stack with logging middleware.
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
                $this->logger,
                new RedactingMessageFormatter(
                    new MessageFormatter(self::LOG_FORMAT),
                ),
                LogLevel::DEBUG,
            ), 'canadapost_api_log');
        }

        return $handlerStack;
    }

    /**
     * Build and send HTTP request.
     *
     * @param array<string, list<string>|string> $headers
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
}
