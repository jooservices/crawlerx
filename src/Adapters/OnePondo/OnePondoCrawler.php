<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\OnePondoUrlDetectRules;
use JOOservices\CrawlerX\Adapters\OnePondo\Types\Detail;
use JOOservices\CrawlerX\Adapters\OnePondo\Types\Listing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class OnePondoCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use OnePondoUrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    protected array $options = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json,text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,ja;q=0.8',
        ],
    ];

    public function name(): string
    {
        return 'onepondo';
    }
}
