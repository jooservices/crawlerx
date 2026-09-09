<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Response;

final class MapCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param  array<string, string>  $responses
     */
    public function __construct(private readonly array $responses)
    {
    }

    public function get(string $url): CrawlHttpResponse
    {
        $html = $this->responses[$url] ?? '';

        return new PsrCrawlHttpResponse(new Response(200, [], $html));
    }
}
