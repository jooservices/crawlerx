<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Contracts\PerformerDetailCapable;
use JOOservices\CrawlerX\Contracts\PerformerListingCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

/**
 * Maps performer capabilities to listing/detail implementations for adapters that share parsers.
 */
trait AliasesPerformerCapabilities
{
    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return $this->listing($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return $this->detail($request);
    }
}

/** @phpstan-require-implements PerformerListingCapable */
/** @phpstan-require-implements PerformerDetailCapable */
