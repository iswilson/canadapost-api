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
 *   - HTTP request/response body XML fields with customer PII:
 *       - Rating: postal code, zip code.
 *       - Tracking: destination postal ID, customer reference fields, signatory
 *         name.
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

    private const array REDACTED_ELEMENTS = [
        // Rating.
        'postal-code',
        'zip-code',
        // Tracking.
        'destination-postal-id',
        'customer-ref-1',
        'customer-ref-2',
        'signatory-name',
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

        // Redact sensitive XML elements.
        $elements = implode('|', self::REDACTED_ELEMENTS);
        return (string) preg_replace(
            "#<({$elements})>[^<]*</\\1>#",
            '<$1>' . self::REDACTED_MESSAGE . '</$1>',
            $this->inner->format($request, $response, $error),
        );
    }
}
