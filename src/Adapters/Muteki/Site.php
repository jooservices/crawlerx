<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muteki;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class Site
{
    public const BASE_URL = 'https://www.mutekimuteki.com';

    public const MAKER = 'MUTEKI';

    public static function config(): SiteConfig
    {
        return new SiteConfig(baseUrl: self::BASE_URL, maker: self::MAKER, plainPerformers: true);
    }
}
