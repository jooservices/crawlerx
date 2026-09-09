<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Contracts\SiteAdapter;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * Dispatches crawl requests to segregated capability interfaces (ISP-compliant).
 */
final class AdapterExecutor
{
    public function execute(SiteAdapter $adapter, CrawlRequestDto $request): CrawlListResultDto|CrawlItemResultDto
    {
        return match ($request->type) {
            CrawlType::Listing => $this->listing($adapter, $request),
            CrawlType::Detail => $this->detail($adapter, $request),
            CrawlType::PerformerListing => $this->performerListing($adapter, $request),
            CrawlType::PerformerDetail => $this->performerDetail($adapter, $request),
        };
    }

    public function listing(SiteAdapter $adapter, CrawlRequestDto $request): CrawlListResultDto
    {
        $this->prepareAdapter($adapter, $request);

        if (! $adapter instanceof ListingCapable) {
            throw new CrawlParseException(sprintf('%s does not implement ListingCapable.', $adapter::class));
        }

        return $adapter->listing($request);
    }

    public function detail(SiteAdapter $adapter, CrawlRequestDto $request): CrawlItemResultDto
    {
        $this->prepareAdapter($adapter, $request);

        if (! $adapter instanceof DetailCapable) {
            throw new CrawlParseException(sprintf('%s does not implement DetailCapable.', $adapter::class));
        }

        return $adapter->detail($request);
    }

    public function performerListing(SiteAdapter $adapter, CrawlRequestDto $request): CrawlListResultDto
    {
        $this->prepareAdapter($adapter, $request);

        if (! $adapter instanceof PerformerListingCapable) {
            throw new CrawlParseException(sprintf('%s does not implement PerformerListingCapable.', $adapter::class));
        }

        return $adapter->performerListing($request);
    }

    public function performerDetail(SiteAdapter $adapter, CrawlRequestDto $request): CrawlItemResultDto
    {
        $this->prepareAdapter($adapter, $request);

        if (! $adapter instanceof PerformerDetailCapable) {
            throw new CrawlParseException(sprintf('%s does not implement PerformerDetailCapable.', $adapter::class));
        }

        return $adapter->performerDetail($request);
    }

    private function prepareAdapter(SiteAdapter $adapter, CrawlRequestDto $request): void
    {
        if ($adapter instanceof AbstractBaseCrawler) {
            $adapter->prepareRequest($request);
        }
    }
}
