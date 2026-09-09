<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

interface PerformerDetailCapable
{
    public function performerDetail(CrawlRequestDto $request): CrawlItemResultDto;
}
