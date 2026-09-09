<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\AliasesPerformerCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JableUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Jable\Types\Detail;
use JOOservices\CrawlerX\Adapters\Jable\Types\Listing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class JableCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use AliasesPerformerCapabilities;
    use DetectsUrls;
    use JableUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://en.jable.tv',
        'timeout' => 30,
    ];

    public function name(): string
    {
        return 'jable';
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
