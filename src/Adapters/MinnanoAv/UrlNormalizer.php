<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv;

final class UrlNormalizer
{
    public function absolute(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return rtrim($origin, '/') . '/' . ltrim($href, '/');
    }

    public function canonicalActressUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        if (preg_match('#/(actress\d+\.html)$#i', $path, $match) !== 1) {
            return $url;
        }

        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return rtrim($origin, '/') . '/' . $match[1];
    }
}
