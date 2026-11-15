<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Adapters\Tma\Types\Detail;
use JOOservices\CrawlerX\Adapters\Tma\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class TmaCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'tma';
    }
}
