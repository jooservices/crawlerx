<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\Detail;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\Listing;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\PerformerListing;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class DahliaCrawler extends SimpleHtmlCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    public function name(): string
    {
        return 'dahlia';
    }

    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client))->execute($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
