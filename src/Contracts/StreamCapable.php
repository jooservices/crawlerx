<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\StreamResultDto;

interface StreamCapable
{
    public function stream(CrawlRequestDto $request): StreamResultDto;
}
