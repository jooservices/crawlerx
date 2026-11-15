<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

/**
 * Product slugs in TMA URLs: `/products/<slug>` and `/collections/<collection>/products/<slug>`.
 */
final class ProductUrl
{
    private const SLUG_PATTERN = '#/products/([^/]+)/?$#';

    private const CODE_PATTERN = '/^[a-z0-9]+-\d+$/i';

    /** The raw (percent-encoded) slug, or null when the URL is not a product URL. */
    public static function slug(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match(self::SLUG_PATTERN, $path, $match) === 1 ? $match[1] : null;
    }

    public static function canonical(string $slug): string
    {
        return Site::BASE_URL . '/products/' . $slug;
    }

    public static function externalId(string $slug): string
    {
        return strtolower(rawurldecode($slug));
    }

    /** The product code (`T38-073`) when the slug is one; Japanese slugs have no code. */
    public static function code(string $slug): ?string
    {
        $decoded = rawurldecode($slug);

        return preg_match(self::CODE_PATTERN, $decoded) === 1 ? strtoupper($decoded) : null;
    }
}
