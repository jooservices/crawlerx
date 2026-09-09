<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

interface CrawlHttpClient
{
    public function get(string $url): CrawlHttpResponse;
}
