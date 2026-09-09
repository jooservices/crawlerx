<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avfan;

use JOOservices\CrawlerX\Adapters\Avfan\Types\Detail;
use JOOservices\CrawlerX\Adapters\Avfan\Types\Listing;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\AvfanUrlDetectRules;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;

final class AvfanCrawler extends SimpleHtmlCrawler implements UrlDetectCapable
{
    use AvfanUrlDetectRules;
    use DetectsUrls;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    protected array $options = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ja,en;q=0.9',
        ],
    ];

    public function name(): string
    {
        return 'avfan';
    }
}
