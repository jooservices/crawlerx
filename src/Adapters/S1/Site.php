<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\S1;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://s1s1s1.com', maker: 'S1 NO.1 STYLE');
    }
}
