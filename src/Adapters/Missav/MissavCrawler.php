<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\MissavUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Missav\Types\Detail;
use JOOservices\CrawlerX\Adapters\Missav\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class MissavCrawler extends AbstractBaseCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use MissavUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://missav.to',
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
    ];

    public function name(): string
    {
        return 'missav';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client))->execute($request);
    }
}
