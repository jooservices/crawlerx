<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

interface CrawlHttpClient
{
    /**
     * @param  array<string, string>  $headers  extra request headers (for example a Cookie or Referer for a follow-up request)
     */
    public function get(string $url, array $headers = []): CrawlHttpResponse;
}
