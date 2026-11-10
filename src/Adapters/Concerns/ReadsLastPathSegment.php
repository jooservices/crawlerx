<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads the last path segment of a URL.
 */
trait ReadsLastPathSegment
{
    /** The last path segment (`/a/b/c/` gives `c`), or null for an empty path unless it is explicitly retained. */
    protected function lastPathSegment(string $url, bool $allowEmptySegment = false): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || (! $allowEmptySegment && trim($path, '/') === '')) {
            return null;
        }

        return trim(basename($path), '/');
    }
}
