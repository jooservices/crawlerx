<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avjoho;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\PerformerOnlyCapabilities;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\AvjohoUrlDetectRules;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

final class AvjohoCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use PerformerOnlyCapabilities;
    use DetectsUrls;
    use AvjohoUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://db.avjoho.com',
    ];

    public function name(): string
    {
        return 'avjoho';
    }

    protected function parsePerformerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new PerformerListing($this->client))->execute($request);
    }

    protected function parsePerformerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
