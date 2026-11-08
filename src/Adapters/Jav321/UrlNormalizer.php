<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;

final class UrlNormalizer
{
    use NormalizesUrls;

    public function canonical(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return $url;
        }

        if (! $this->isJav321Host($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        if ($scheme === '') {
            $scheme = 'https';
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . self::CANONICAL_HOST . $path . $query;
    }

    public function absoluteAndCanonical(string $baseUrl, string $href): string
    {
        return $this->canonical($this->absolute($baseUrl, $href));
    }

    private function isJav321Host(string $host): bool
    {
        $host = strtolower($host);

        return $host === self::CANONICAL_HOST
            || $host === Site::HOST
            || str_ends_with($host, '.' . Site::HOST);
    }

    private const CANONICAL_HOST = 'www.jav321.com';
}
