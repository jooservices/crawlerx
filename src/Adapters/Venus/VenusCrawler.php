<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Venus;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Adapters\Venus\Types\Detail;
use JOOservices\CrawlerX\Adapters\Venus\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class VenusCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'venus';
    }
}
