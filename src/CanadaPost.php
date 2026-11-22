<?php

declare(strict_types=1);

namespace iswilson\CanadaPost;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Platform\DevProgram\DevProgram;
use Psr\Log\LoggerInterface;

/**
 * Create clients for Canada Post platforms.
 */
final class CanadaPost
{
    private function __construct() {}

    /**
     * Canada Post Developer Program (XML).
     *
     * @param string $username
     *   Developer Program API user ID.
     * @param string $password
     *   Developer Program API password.
     * @param string $customerNumber
     *   Customer number of the owner of the mail (mailed on behalf of customer).
     * @param string|null $contractId
     *   Optional contract ID for commercial customers.
     * @param bool $sandbox
     *   Platform environment, default to sandbox/development.
     * @param LoggerInterface|null $logger
     *   Optional logging interface.
     * @param float|null $timeout
     *   Total timeout of the request in seconds (default 30).
     * @param callable|null $handler
     *   Internal: Optional Guzzle handler for tests.
     *
     * @throws InvalidArgumentException
     *
     * @return DevProgram
     */
    public static function devProgram(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $customerNumber,
        ?string $contractId = null,
        bool $sandbox = true,
        ?LoggerInterface $logger = null,
        ?float $timeout = null,
        ?callable $handler = null,
    ): DevProgram {
        return new DevProgram(
            username: $username,
            password: $password,
            customerNumber: $customerNumber,
            contractId: $contractId,
            sandbox: $sandbox,
            logger: $logger,
            timeout: $timeout,
            handler: $handler,
        );
    }
}
