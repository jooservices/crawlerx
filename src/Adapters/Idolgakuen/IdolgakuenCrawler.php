<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Idolgakuen;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Types\Detail;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class IdolgakuenCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'idolgakuen';
    }
}
