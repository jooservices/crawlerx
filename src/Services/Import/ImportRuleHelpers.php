<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services\Import;

final class ImportRuleHelpers
{
    /**
     * @return array{host: string, path: string, query: string}|null
     */
    public static function parseUrl(string $url): ?array
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $query = is_string($parts['query'] ?? null) ? $parts['query'] : '';

        return [
            'host' => $host,
            'path' => $path,
            'query' => $query,
        ];
    }

    public static function hostMatches(string $host, string $pattern): bool
    {
        $host = strtolower($host);
        $pattern = strtolower($pattern);

        if ($host === $pattern) {
            return true;
        }

        return str_ends_with($host, '.' . $pattern);
    }

    public static function hostFromUrl(string $baseUrl): ?string
    {
        $parts = parse_url($baseUrl);

        return is_array($parts) && is_string($parts['host'] ?? null)
            ? strtolower($parts['host'])
            : null;
    }

    public static function pathContains(string $path, string $needle): bool
    {
        return str_contains(strtolower($path), strtolower($needle));
    }

    public static function pathMatches(string $path, string $pattern): bool
    {
        return preg_match($pattern, $path) === 1;
    }

    public static function queryHas(string $query, string $param): bool
    {
        if ($query === '') {
            return false;
        }

        parse_str($query, $params);

        $value = $params[$param] ?? null;

        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  list<string>  $reserved
     */
    public static function singleSegmentSlug(string $path, array $reserved): bool
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '' || str_contains($trimmed, '/')) {
            return false;
        }

        $segment = strtolower($trimmed);

        return ! in_array($segment, $reserved, true)
            && preg_match('/^[a-z0-9][a-z0-9-]*$/', $segment) === 1;
    }
}
