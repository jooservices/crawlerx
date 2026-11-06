<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wanz;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class WanzCrawler extends FamilyCrawler
{
    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'wanz';
    }
}
