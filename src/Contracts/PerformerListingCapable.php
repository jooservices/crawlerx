<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

interface PerformerListingCapable
{
    public function performerListing(CrawlRequestDto $request): CrawlListResultDto;
}
