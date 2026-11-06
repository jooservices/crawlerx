<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bi;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://bi-av.com', maker: 'BI');
    }
}
