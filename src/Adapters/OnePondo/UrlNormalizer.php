<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;

final class UrlNormalizer
{
    use NormalizesUrls {
        absolute as private packageAbsolute;
    }

    private const ASSET_BASE = 'https://www.1pondo.tv/';

    public function absolute(string $baseUrl, string $url): string
    {
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'https';

            return $scheme . ':' . $url;
        }

        return $this->packageAbsolute($baseUrl, $url);
    }

    public function assetUrl(string $path): string
    {
        return rtrim(self::ASSET_BASE, '/') . '/' . ltrim($path, '/');
    }

    public function galleryImageUrl(string $path): string
    {
        return rtrim(self::ASSET_BASE, '/') . '/dyn/dla/images/' . ltrim($path, '/');
    }

    public function movieUrl(string $movieId): string
    {
        return 'https://en.1pondo.tv/movies/' . trim($movieId, '/') . '/';
    }
}
