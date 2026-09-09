<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDatabase;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\AliasesPerformerCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JavDatabaseUrlDetectRules;
use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavDatabase\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JavDatabaseCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use AliasesPerformerCapabilities;
    use DetectsUrls;
    use JavDatabaseUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://www.javdatabase.com',
    ];

    public function name(): string
    {
        return 'javdatabase';
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
