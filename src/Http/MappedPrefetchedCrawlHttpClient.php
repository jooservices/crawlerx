<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Nyholm\Psr7\Response;

final readonly class MappedPrefetchedCrawlHttpClient implements CrawlHttpClient
{
    /**
     * @param  array<string, array{body: string, status?: int, headers?: array<string, string>}>  $responses
     * @param  array<string, string>  $defaultHeaders
     */
    public function __construct(
        private array $responses,
        private string $defaultBody = '',
        private int $defaultStatus = 404,
        private array $defaultHeaders = ['Content-Type' => 'application/json'],
    ) {
    }

    public function get(string $url): CrawlHttpResponse
    {
        $config = $this->responses[$url] ?? null;

        if ($config === null) {
            return new PsrCrawlHttpResponse(new Response(
                $this->defaultStatus,
                $this->defaultHeaders,
                $this->defaultBody,
            ));
        }

        $headers = $config['headers'] ?? $this->defaultHeaders;

        return new PsrCrawlHttpResponse(new Response(
            $config['status'] ?? 200,
            $headers,
            $config['body'],
        ));
    }
}
