<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Falenogroup\Types\Detail;
use JOOservices\CrawlerX\Adapters\Falenogroup\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class FalenogroupCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'falenogroup';
    }
}
