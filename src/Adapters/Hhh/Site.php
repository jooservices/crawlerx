<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Hhh;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://hhh-av.com', maker: 'HHH', unlinkedPerformers: true);
    }
}
