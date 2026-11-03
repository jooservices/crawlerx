<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Ideapocket;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://ideapocket.com', maker: 'IDEAPOCKET');
    }
}
