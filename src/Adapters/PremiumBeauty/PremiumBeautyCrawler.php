<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\PremiumBeauty;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;

final class PremiumBeautyCrawler extends FamilyCrawler
{
    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'premiumbeauty';
    }
}
