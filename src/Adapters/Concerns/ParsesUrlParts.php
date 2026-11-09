<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads identifiers and page numbers out of a URL's path or query string.
 *
 * Adapters keep their own route patterns; this trait owns the parse_url/preg_match plumbing they all repeated.
 */
trait ParsesUrlParts
{
    /** The last non-empty path segment (`/a/b/c/` gives `c`), or null for an empty path. */
    protected function lastPathSegment(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || trim($path, '/') === '') {
            return null;
        }

        return trim(basename($path), '/');
    }

    /** First capture group of $pattern matched against the URL path, or null when it does not match. */
    protected function pathMatch(string $url, string $pattern): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match($pattern, $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /** First capture group of $pattern matched against the URL path as a positive page number. */
    protected function pathNumber(string $url, string $pattern): ?int
    {
        $number = $this->pathMatch($url, $pattern);

        return $number !== null && (int) $number > 0 ? (int) $number : null;
    }

    /** A scalar query-string value, or null when the parameter is absent, empty or an array. */
    protected function queryParam(string $url, string $name): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $value = $params[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** A positive page number from a query-string parameter, or null. */
    protected function queryNumber(string $url, string $name): ?int
    {
        $value = $this->queryParam($url, $name);

        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
