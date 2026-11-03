<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom;

use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Detail;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\AbstractCatalogCrawler;
use JOOservices\CrawlerX\Adapters\Shared\Catalog\CatalogDefinition;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

/** Listing + detail crawler shared by the Caribbeancom family; a concrete crawler only supplies its Profile. */
abstract class AbstractCaribbeanCrawler extends AbstractCatalogCrawler
{
    abstract protected function profile(): Profile;

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client, $this->profile()))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client, $this->profile()))->execute($request);
    }

    protected function definition(): CatalogDefinition
    {
        return $this->profile()->definition();
    }
}
