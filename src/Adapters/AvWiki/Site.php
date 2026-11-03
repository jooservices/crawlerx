<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

final class Site
{
    public const BASE_URL = 'https://av-wiki.net';

    public const POSTS_URL = self::BASE_URL . '/wp-json/wp/v2/posts';

    public static function listingApiUrl(string $sourceUrl, int $page): string
    {
        $parts = parse_url($sourceUrl);
        $path = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $query = [];
        if (is_array($parts) && is_string($parts['query'] ?? null)) {
            parse_str($parts['query'], $query);
        }

        if (! str_ends_with($path, '/wp-json/wp/v2/posts')) {
            $query = ['per_page' => 100, 'page' => $page, '_embed' => 1];
        } else {
            $query['per_page'] = self::pageSize($sourceUrl);
            $query['_embed'] = 1;
            $query['page'] = $page;
        }

        return self::POSTS_URL . '?' . self::queryString($query);
    }

    public static function listingPageUrl(string $sourceUrl, int $page): string
    {
        return self::listingApiUrl($sourceUrl, $page);
    }

    public static function pageSize(string $sourceUrl): int
    {
        $query = parse_url($sourceUrl, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return 100;
        }
        parse_str($query, $params);
        $size = $params['per_page'] ?? null;

        return is_numeric($size) && (int) $size > 0 ? min(100, (int) $size) : 100;
    }

    public static function detailApiUrl(string $sourceUrl): string
    {
        $parts = parse_url($sourceUrl);
        $path = is_array($parts) && is_string($parts['path'] ?? null) ? $parts['path'] : '';
        if (preg_match('#^/wp-json/wp/v2/posts/\d+/?$#', $path) === 1) {
            return self::BASE_URL . $path . '?_embed=1';
        }

        if (str_ends_with($path, '/wp-json/wp/v2/posts')) {
            $query = [];
            if (is_string($parts['query'] ?? null)) {
                parse_str($parts['query'], $query);
            }
            if (is_string($query['id'] ?? null) && ctype_digit($query['id'])) {
                return self::POSTS_URL . '/' . $query['id'] . '?_embed=1';
            }
            $query['_embed'] = 1;

            return self::POSTS_URL . '?' . self::queryString($query);
        }

        $query = [];
        if (is_array($parts) && is_string($parts['query'] ?? null)) {
            parse_str($parts['query'], $query);
        }
        if (is_string($query['p'] ?? null) && ctype_digit($query['p'])) {
            return self::POSTS_URL . '/' . $query['p'] . '?_embed=1';
        }

        $slug = rawurldecode(trim(basename($path), '/'));

        return self::detailBySlugUrl($slug);
    }

    public static function detailBySlugUrl(string $slug): string
    {
        return self::POSTS_URL . '?slug=' . rawurlencode($slug) . '&_embed=1';
    }

    public static function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return self::BASE_URL . $url;
        }

        return preg_match('~^https?://~i', $url) === 1 ? $url : self::BASE_URL . '/' . ltrim($url, '/');
    }

    /** @param array<int|string, mixed> $query */
    private static function queryString(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }
}
