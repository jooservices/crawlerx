<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Matches a route pattern against a URL path. Adapters keep their own route regex.
 */
trait MatchesUrlPath
{
    /** First capture group of $pattern matched against the URL path, or null when it does not match. */
    protected function pathMatch(string $url, string $pattern): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match($pattern, $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
