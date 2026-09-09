<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Response;

final readonly class PrefetchedCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private string $html,
        private int $status = 200,
        private ?string $finalUrl = null,
        private array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public function get(string $url): CrawlHttpResponse
    {
        return new PsrCrawlHttpResponse(new Response(
            $this->status,
            $this->headers,
            $this->html,
        ));
    }

    public function finalUrl(): ?string
    {
        return $this->finalUrl;
    }
}
