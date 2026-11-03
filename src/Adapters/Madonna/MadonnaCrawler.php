<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Madonna;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class MadonnaCrawler extends FamilyCrawler
{
    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'madonna';
    }
}
