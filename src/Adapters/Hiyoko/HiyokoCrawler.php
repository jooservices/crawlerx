<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Hiyoko;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Hiyoko\Types\Detail;
use JOOservices\CrawlerX\Adapters\Hiyoko\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class HiyokoCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'hiyoko';
    }
}
