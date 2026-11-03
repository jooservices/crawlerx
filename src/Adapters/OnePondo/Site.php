<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(
            baseUrl: 'https://en.1pondo.tv',
            assetBaseUrl: 'https://www.1pondo.tv/',
            moviePath: '/movies/{id}/',
            movieUrlPattern: Selectors::MOVIE_URL_PATTERN,
            galleryPath: '/dyn/dla/json/movie_gallery/{id}.json',
            galleryImagePath: '/dyn/dla/images/',
            maker: '1Pondo',
            label: 'OnePondo',
            gallerySource: 'onepondo_gallery',
            movieIdPattern: Selectors::MOVIE_ID_PATTERN,
        );
    }
}
