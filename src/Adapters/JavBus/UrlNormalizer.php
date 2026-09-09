<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;

final class UrlNormalizer
{
    use NormalizesUrls;

    private const CANONICAL_HOST = 'www.javbus.com';

    public function canonical(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return $url;
        }

        if (! $this->isJavBusHost($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        if ($scheme === '') {
            $scheme = 'https';
        }
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . self::CANONICAL_HOST . $path . $query . $fragment;
    }

    public function absoluteAndCanonical(string $baseUrl, string $href): string
    {
        return $this->canonical($this->absolute($baseUrl, $href));
    }

    private function isJavBusHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === self::CANONICAL_HOST
            || $host === 'javbus.com'
            || str_contains($host, 'javbus');
    }
}
