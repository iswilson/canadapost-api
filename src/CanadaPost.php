<?php

declare(strict_types=1);

namespace iswilson\CanadaPost;

use iswilson\CanadaPost\Platform\DevProgram\DevProgram;

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
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public static function devProgram(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $customerNumber,
        ?string $contractId = null,
        bool $sandbox = true,
    ): DevProgram {
        return new DevProgram(
            $username,
            $password,
            $customerNumber,
            $contractId,
            $sandbox,
        );
    }
}
