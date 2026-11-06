<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Vav;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://v-av.com', maker: 'V-AV', plainPerformers: true);
    }
}
