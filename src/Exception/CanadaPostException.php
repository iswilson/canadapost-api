<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Exception;

/**
 * Request to Canada Post API failed.
 */
class CanadaPostException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param list<array{code: string, message: string}> $errors
     *   List of Canada Post API response error codes and messages.
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?int $httpStatusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Search error codes for specified code.
     */
    public function hasErrorCode(string $code): bool
    {
        return in_array($code, array_column($this->errors, 'code'), true);
    }
}
