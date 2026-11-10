<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads scalar query-string parameters of a URL.
 */
trait ReadsQueryParams
{
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

    /** Whether a query parameter has a non-empty scalar value. */
    protected function hasQueryParam(string $url, string $name): bool
    {
        $value = $this->queryParam($url, $name);

        return $value !== null && trim($value) !== '';
    }

    /** The first present query-string parameter among $names (aliases of one value) as a trimmed string, or null. */
    protected function firstQueryParam(string $url, string ...$names): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $value = null;
        foreach ($names as $name) {
            $value = $params[$name] ?? null;
            if ($value !== null) {
                break;
            }
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
