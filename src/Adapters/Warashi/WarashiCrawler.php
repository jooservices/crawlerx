<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Warashi;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\AliasesPerformerCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\WarashiUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Warashi\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class WarashiCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use AliasesPerformerCapabilities;
    use DetectsUrls;
    use WarashiUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://warashi-asian-pornstars.fr',
    ];

    public function name(): string
    {
        return 'warashi';
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
