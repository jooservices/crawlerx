<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Concerns\UrlDetect\JavBusUrlDetectRules;
use JOOservices\CrawlerX\Adapters\JavBus\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavBus\Types\PerformerListing;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;

final class JavBusCrawler extends AbstractBaseCrawler implements PerformerDetailCapable, PerformerListingCapable, UrlDetectCapable
{
    use DetectsUrls {
        detectUrl as private detectJavBusUrl;
    }
    use JavBusUrlDetectRules;

    protected array $options = [
        'base_uri' => 'https://www.javbus.com',
    ];

    public function name(): string
    {
        return 'javbus';
    }

    public function detectUrl(string $url): UrlDetectionResult
    {
        $result = $this->detectJavBusUrl($url);
        $path = parse_url($url, PHP_URL_PATH);
        if (! $result->matched || ($path !== null && $path !== '' && $path !== '/')) {
            return $result;
        }

        return new UrlDetectionResult(
            hostMatched: $result->hostMatched,
            matched: true,
            crawlType: $result->crawlType,
            entity: $result->entity,
            urlType: $result->urlType,
            normalizedUrl: 'https://www.javbus.com/en/',
        );
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

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new PerformerDetail($this->client))->execute($request);
    }
}
