<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use Symfony\Component\DomCrawler\UriResolver;

trait NormalizesUrls
{
    public function absolute(string $baseUrl, string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'https';

            return $scheme . ':' . $url;
        }

        if ($baseUrl !== '' && parse_url($baseUrl, PHP_URL_HOST) !== null) {
            return UriResolver::resolve($url, $baseUrl);
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? trim($baseUrl, '/'));

        if (str_starts_with($url, '?')) {
            return $origin . ($parts['path'] ?? '/') . $url;
        }

        return rtrim($origin, '/') . '/' . ltrim($url, '/');
    }
}
