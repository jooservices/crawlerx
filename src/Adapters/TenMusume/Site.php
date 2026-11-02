<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TenMusume;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(
            baseUrl: 'https://www.10musume.com',
            assetBaseUrl: 'https://www.10musume.com/',
            moviePath: '/movies/{id}/',
            movieUrlPattern: Selectors::MOVIE_URL_PATTERN,
            galleryPath: '/dyn/dla/json/movie_gallery/{id}.json',
            galleryImagePath: '/dyn/dla/images/',
            maker: '10Musume',
            label: 'TenMusume',
            gallerySource: '10musume_gallery',
            movieIdPattern: Selectors::MOVIE_ID_PATTERN,
        );
    }
}
