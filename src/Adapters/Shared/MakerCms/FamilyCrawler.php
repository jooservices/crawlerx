<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms;

use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\SimpleHtmlCrawler;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

abstract class FamilyCrawler extends SimpleHtmlCrawler implements PerformerDetailCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    protected string $listingType = Listing::class;

    protected string $detailType = Detail::class;

    abstract protected function siteConfig(): SiteConfig;

    protected function makeType(string $typeClass): TypeInterface
    {
        return new $typeClass($this->client, $this->siteConfig());
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client, $this->siteConfig()))->execute($request);
    }
}
