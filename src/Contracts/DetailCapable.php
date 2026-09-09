<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

interface DetailCapable
{
    public function detail(CrawlRequestDto $request): CrawlItemResultDto;
}
