<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Javbtc;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JavbtcUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Javbtc\Types\Detail;
use JOOservices\CrawlerX\Adapters\Javbtc\Types\Listing;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JavbtcCrawler extends AbstractBaseCrawler implements UrlDetectCapable
{
    use DetectsUrls;
    use JavbtcUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://javbtc.com',
        'timeout' => 30,
    ];

    public function name(): string
    {
        return 'javbtc';
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
