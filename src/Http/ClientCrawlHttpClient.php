<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;

final readonly class ClientCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(private HttpClient $httpClient, private array $headers = [])
    {
    }

    public function get(string $url, array $headers = []): CrawlHttpResponse
    {
        $builder = $this->httpClient->requestBuilder()->get($url);
        $merged = array_merge($this->headers, $headers);
        if ($merged !== []) {
            $builder = $builder->withHeaders($merged);
        }
        $request = $builder->build();

        return new PsrCrawlHttpResponse($this->httpClient->sendRequest($request->toPsr()));
    }
}
