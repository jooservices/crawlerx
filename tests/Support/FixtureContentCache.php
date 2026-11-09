<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use RuntimeException;

final class FixtureContentCache
{
    /** @var array<string, string> */
    private static array $contents = [];

    public static function read(string $path): string
    {
        $realPath = realpath($path);
        $cacheKey = $realPath === false ? $path : $realPath;

        if (array_key_exists($cacheKey, self::$contents)) {
            return self::$contents[$cacheKey];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Fixture not found: {$path}");
        }

        self::$contents[$cacheKey] = $contents;

        return $contents;
    }
}
