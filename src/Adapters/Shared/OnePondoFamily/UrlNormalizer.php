<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;

class UrlNormalizer
{
    use NormalizesUrls {
        absolute as private packageAbsolute;
    }

    public function __construct(private readonly SiteConfig $config)
    {
    }

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
        return rtrim($this->config->assetBaseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function galleryImageUrl(string $path): string
    {
        return rtrim($this->config->assetBaseUrl, '/') . $this->config->galleryImagePath . ltrim($path, '/');
    }

    public function movieUrl(string $movieId): string
    {
        return rtrim($this->config->baseUrl, '/') . str_replace('{id}', trim($movieId, '/'), $this->config->moviePath);
    }
}
