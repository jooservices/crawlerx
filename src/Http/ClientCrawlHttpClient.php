<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;

final readonly class ClientCrawlHttpClient implements CrawlHttpClient
{
    public function __construct(private HttpClient $httpClient)
    {
    }

    public function get(string $url): CrawlHttpResponse
    {
        $request = $this->httpClient->requestBuilder()->get($url)->build();

        return new PsrCrawlHttpResponse($this->httpClient->sendRequest($request->toPsr()));
    }
}
