<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Pacopacomama;

use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(
            baseUrl: 'https://www.pacopacomama.com',
            assetBaseUrl: 'https://www.pacopacomama.com/',
            moviePath: '/movies/{id}/',
            movieUrlPattern: '#^https://(?:www\.)?pacopacomama\.com/movies/(\d{6}_\d{2,3})/?$#i',
            galleryPath: '/dyn/dla/json/movie_gallery/{id}.json',
            galleryImagePath: '/dyn/dla/images/',
            maker: 'Pacopacomama',
            label: 'Pacopacomama',
            gallerySource: 'pacopacomama_gallery',
            movieIdPattern: '#^(\d{6}_\d{2,3})$#',
        );
    }
}
