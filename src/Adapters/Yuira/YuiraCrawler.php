<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Adapters\Yuira\Types\Detail;
use JOOservices\CrawlerX\Adapters\Yuira\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class YuiraCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'yuira';
    }
}
