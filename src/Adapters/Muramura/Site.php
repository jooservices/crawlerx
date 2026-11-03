<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muramura;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(
            baseUrl: 'https://www.muramura.tv',
            assetBaseUrl: 'https://www.muramura.tv',
            moviePath: '/movies/{id}/',
            movieUrlPattern: Selectors::MOVIE_URL_PATTERN,
            galleryPath: '/dyn/phpauto/movie_galleries/movie_id/{id}.json',
            galleryImagePath: '/assets/',
            maker: 'Muramura',
            label: 'Muramura',
            gallerySource: 'muramura_gallery',
            movieIdPattern: Selectors::MOVIE_ID_PATTERN,
            galleryFlagField: 'Gallery',
        );
    }
}
