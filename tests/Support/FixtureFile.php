<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Support;

/**
 * Helper for loading test fixtures.
 */
final class FixtureFile
{
    private const string BASE_DIR = __DIR__ . '/../Fixtures';

    public static function load(string $name): string
    {
        $filePath = self::BASE_DIR . '/' . $name;
        if (!is_file($filePath)) {
            throw new \RuntimeException(sprintf('Fixture does not exist: %s.', $name));
        }

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            throw new \RuntimeException(sprintf('Failed to load fixture: %s.', $name));
        }

        return $fileContents;
    }
}
