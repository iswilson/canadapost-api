<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Exception;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Exception when a client error is encountered (4xx codes).
 *
 * In addition to the request and the response, it makes available the parsed
 * response body.
 */
class ClientException extends RequestException implements ExceptionInterface
{
    /** @var array<string, mixed> */
    private array $responseBody;

    /**
     * @param array<string, mixed> $responseBody
     * @param array<string, mixed> $handlerContext
     */
    public function __construct(
        string $message,
        array $responseBody,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
        array $handlerContext = [],
    ) {
        $this->responseBody = $responseBody;

        parent::__construct(
            $message,
            $request,
            $response,
            $previous,
            $handlerContext,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
