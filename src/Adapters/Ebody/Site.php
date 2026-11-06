<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Ebody;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: 'https://av-e-body.com', maker: 'E-BODY');
    }
}
