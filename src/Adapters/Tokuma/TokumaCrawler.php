<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tokuma;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Tokuma\Types\Detail;
use JOOservices\CrawlerX\Adapters\Tokuma\Types\Listing;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class TokumaCrawler extends AbstractBaseCrawler implements DetailCapable, ListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    public function name(): string
    {
        return 'tokuma';
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
