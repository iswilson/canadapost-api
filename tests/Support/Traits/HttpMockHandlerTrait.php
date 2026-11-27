<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Support\Traits;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Create and return handler stack with history middleware.
 */
trait HttpMockHandlerTrait
{
    /**
     * @param array{} $container
     * @param list<ResponseInterface|\Throwable> $responses
     *
     * @param-out list<array{
     *     request: RequestInterface,
     *     response: ResponseInterface|null,
     *     error: mixed,
     *     options: array<string, mixed>,
     * }> $container
     *
     * @return HandlerStack<callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>>
     */
    private function handlerStack(array &$container, array $responses): HandlerStack
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);

        // Add history middleware that will record each request.
        $history = Middleware::history($container); // @phpstan-ignore paramOut.type
        $handlerStack->push($history);

        return $handlerStack;
    }
}
