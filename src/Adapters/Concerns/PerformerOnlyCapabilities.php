<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

/**
 * Shares the public performer contract for sites which do not expose movie pages.
 *
 * @phpstan-require-implements \JOOservices\CrawlerX\Contracts\PerformerListingCapable
 * @phpstan-require-implements \JOOservices\CrawlerX\Contracts\PerformerDetailCapable
 */
trait PerformerOnlyCapabilities
{
    public function performerListing(CrawlRequestDto $request): CrawlListResultDto
    {
        return $this->parsePerformerListing($request);
    }

    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return $this->parsePerformerDetail($request);
    }

    abstract protected function parsePerformerListing(CrawlRequestDto $request): CrawlListResultDto;

    abstract protected function parsePerformerDetail(CrawlRequestDto $request): CrawlItemResultDto;
}
