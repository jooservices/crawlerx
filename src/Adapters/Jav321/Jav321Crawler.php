<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\Jav321UrlDetectRules;
use JOOservices\CrawlerX\Adapters\Jav321\Types\Detail;
use JOOservices\CrawlerX\Adapters\Jav321\Types\Listing;
use JOOservices\CrawlerX\Adapters\Jav321\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class Jav321Crawler extends AbstractBaseCrawler implements DetailCapable, ListingCapable, PerformerListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use Jav321UrlDetectRules;

    public function name(): string
    {
        return 'jav321';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client))->execute($request);
    }

    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client))->execute($request);
    }
}
