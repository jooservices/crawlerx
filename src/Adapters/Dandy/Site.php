<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy;

use Symfony\Component\DomCrawler\UriResolver;

final class Site
{
    public const BASE_URL = 'https://www.choi-waru.com';

    /** Label groups hosted on the site (`/<label>/`); the sister label hiyoko lives on its own domain. */
    public const LABELS = ['dandy', 'cosmos', 'yonaka', 'dekoboko', 'assmi', 'yuuyake', 'murikuri', 'sun', 'yariman'];

    public static function absolute(string $url): string
    {
        return UriResolver::resolve($url, self::BASE_URL . '/');
    }

    /** @return non-empty-string */
    public static function labelPattern(): string
    {
        return implode('|', self::LABELS);
    }

    /** The work slug (`dandy-880`) of a `/<label>/<slug>/` URL, or null for any other URL. */
    public static function workSlug(string $url): ?string
    {
        return self::workPart($url, 2);
    }

    public static function labelSlug(string $url): ?string
    {
        return self::workPart($url, 1);
    }

    private static function workPart(string $url, int $group): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $pattern = '#^/(' . self::labelPattern() . ')/(?!works/?$|genre/?$)([A-Za-z0-9_-]+)/?$#';

        return is_string($path) && preg_match($pattern, $path, $match) === 1 ? strtolower($match[$group]) : null;
    }
}
