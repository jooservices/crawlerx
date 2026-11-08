<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Rocket;

use InvalidArgumentException;
use Symfony\Component\DomCrawler\UriResolver;

final class Site
{
    public const BASE_URL = 'https://rocket-inc.net';

    public const MAKER = 'ROCKET';

    public const DETAIL_PATH = '/works/';

    public static function absolute(string $url): string
    {
        return UriResolver::resolve($url, self::BASE_URL . '/');
    }

    /**
     * The site serves its uploads through the Jetpack image CDN (`i0.wp.com/<host>/<path>?fit=...`), which resizes and
     * recompresses them. The origin file is the same path on the site host without the query.
     */
    public static function original(string $url): string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        if (preg_match('/^i[0-3]\.wp\.com$/', $host) !== 1 || ! str_starts_with($path, '/' . parse_url(self::BASE_URL, PHP_URL_HOST) . '/')) {
            return $url;
        }

        return 'https:/' . $path;
    }

    public static function detailUrl(string $id): string
    {
        return self::BASE_URL . self::DETAIL_PATH . $id . '/';
    }

    /** The work slug (`rctd-774`) from a `/works/<slug>/` URL. */
    public static function entityId(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#^' . preg_quote(self::DETAIL_PATH, '#') . '([^/]+)/?$#', $path, $match) !== 1 || $match[1] === 'page') {
            throw new InvalidArgumentException('Unrecognized ROCKET work URL.');
        }

        return $match[1];
    }

    /** The decoded last path segment of a URL, the site's own id for a taxonomy term. */
    public static function slug(string $url): string
    {
        return urldecode(basename((string) parse_url($url, PHP_URL_PATH)));
    }
}
