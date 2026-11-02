<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Pacopacomama;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\PacopacomamaUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class PacopacomamaCrawler extends FamilyCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use PacopacomamaUrlDetectRules;

    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'pacopacomama';
    }
}
