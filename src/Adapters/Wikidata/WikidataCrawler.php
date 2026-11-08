<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\Listing;
use JOOservices\CrawlerX\Adapters\Wikidata\Types\PerformerDetail;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class WikidataCrawler extends AbstractBaseCrawler implements ListingCapable, PerformerDetailCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    public function name(): string
    {
        return 'wikidata';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
