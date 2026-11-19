<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Logging;

use GuzzleHttp\MessageFormatterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Redacts content from log messages.
 *
 * The following is replaced with [REDACTED]:
 *   - HTTP request headers: authorization, cookie, proxy-authorization.
 *   - HTTP response headers: set-cookie.
 *   - HTTP request body XML fields with customer PII: postal code, zip code.
 *
 * @internal
 */
final readonly class RedactingMessageFormatter implements MessageFormatterInterface
{
    private const string REDACTED_MESSAGE = '[REDACTED]';

    private const array REDACTED_HEADERS = [
        // Request.
        'Authorization',
        'Cookie',
        'Proxy-Authorization',
        // Response.
        'Set-Cookie',
    ];

    public function __construct(private MessageFormatterInterface $inner) {}

    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $error = null): string
    {
        // Redact sensitive headers from request/response.
        foreach (self::REDACTED_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                $request = $request->withHeader($header, self::REDACTED_MESSAGE);
            }
            if ($response?->hasHeader($header)) {
                $response = $response->withHeader($header, self::REDACTED_MESSAGE);
            }
        }

        // Redact destination postal/zip codes.
        return (string) preg_replace(
            '#<(postal-code|zip-code)>[^<]*</\1>#',
            '<$1>' . self::REDACTED_MESSAGE . '</$1>',
            $this->inner->format($request, $response, $error),
        );
    }
}
