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

    public function __construct(
        $message,
        $responseBody,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Exception $previous = null,
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

    public function getResponseBody()
    {
        return $this->responseBody;
    }
}
