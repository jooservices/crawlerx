<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TenMusume;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\TenMusumeUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class TenMusumeCrawler extends FamilyCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use TenMusumeUrlDetectRules;

    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return '10musume';
    }
}
