<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use Closure;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Client\HttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Response;

final readonly class ClientCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param array<string, string> $headers
     * @param Closure(string): bool|null $beforeOutboundRequest
     */
    public function __construct(
        private HttpClient $httpClient,
        private array $headers = [],
        private ?Closure $beforeOutboundRequest = null,
    ) {
    }

    /** @param array<string, string> $headers */
    public function get(string $url, array $headers = []): CrawlHttpResponse
    {
        if ($this->beforeOutboundRequest !== null && ! ($this->beforeOutboundRequest)($url)) {
            return new PsrCrawlHttpResponse(new Response(429, [
                'Retry-After' => '0',
                'X-CrawlerX-Request-Limit' => 'exceeded',
            ]));
        }

        $builder = $this->httpClient->requestBuilder()->get($url);
        if ($this->beforeOutboundRequest !== null && ! ClientBuilder::isFaked()) {
            // Redirects are additional requests, so callers enforcing outbound limits must handle
            // each hop themselves rather than letting the transport follow them invisibly.
            $builder = $builder->withRedirects(false);
        }

        $merged = array_merge($this->headers, $headers);
        if ($merged !== []) {
            $builder = $builder->withHeaders($merged);
        }
        $request = $builder->build();

        return new PsrCrawlHttpResponse($this->httpClient->send($request->toPsr(), $request->options()));
    }
}
