<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Dandy\Types\Detail;
use JOOservices\CrawlerX\Adapters\Dandy\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class DandyCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'dandy';
    }
}
