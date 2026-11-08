<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravurech;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Gravurech\Types\Listing;
use JOOservices\CrawlerX\Contracts\GalleryCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class GravurechCrawler extends AbstractBaseCrawler implements GalleryCapable, ListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://gravure-ch.com',
    ];

    public function name(): string
    {
        return 'gravurech';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function gallery(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Gallery($this->client))->execute($request);
    }
}
