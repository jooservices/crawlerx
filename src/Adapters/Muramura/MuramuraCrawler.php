<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Muramura;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\MuramuraUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SiteConfig;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class MuramuraCrawler extends FamilyCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use MuramuraUrlDetectRules;

    protected function siteConfig(): SiteConfig
    {
        return Site::config();
    }

    public function name(): string
    {
        return 'muramura';
    }
}
