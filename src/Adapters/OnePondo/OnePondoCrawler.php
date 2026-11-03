<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\UrlDetectRules;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class OnePondoCrawler extends FamilyCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'onepondo';
    }
}
