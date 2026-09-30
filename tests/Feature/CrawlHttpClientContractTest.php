<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\Http\MapCrawlHttpClient;
use JOOservices\CrawlerX\Http\MappedPrefetchedCrawlHttpClient;
use JOOservices\CrawlerX\Http\PrefetchedCrawlHttpClient;
use JOOservices\CrawlerX\Tests\TestCase;

final class CrawlHttpClientContractTest extends TestCase
{
    private const DETAIL_URL = 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json';

    public function test_mapped_client_returns_live_payload_and_default_response(): void
    {
        $payload = $this->loadFixture('onepondo/detail-060426_001.json');
        $client = new MappedPrefetchedCrawlHttpClient(
            responses: [self::DETAIL_URL => ['body' => $payload, 'status' => 201, 'headers' => ['X-Source' => 'live-capture']]],
            defaultStatus: 410,
            defaultHeaders: ['X-Source' => 'fallback'],
        );

        $resolved = $client->get(self::DETAIL_URL)->toPsrResponse();
        $fallback = $client->get('https://en.1pondo.tv/missing.json')->toPsrResponse();

        self::assertSame(201, $resolved->getStatusCode());
        self::assertSame('live-capture', $resolved->getHeaderLine('X-Source'));
        self::assertSame($payload, (string) $resolved->getBody());
        self::assertSame(410, $fallback->getStatusCode());
        self::assertSame('fallback', $fallback->getHeaderLine('X-Source'));
    }

    public function test_prefetched_and_simple_map_clients_preserve_live_payload_contract(): void
    {
        $payload = $this->loadFixture('onepondo/list-newest-0.json');
        $prefetched = new PrefetchedCrawlHttpClient(
            html: $payload,
            status: 202,
            finalUrl: self::DETAIL_URL,
            headers: ['Content-Type' => 'application/json'],
        );
        $mapped = new MapCrawlHttpClient([self::DETAIL_URL => $payload]);

        $prefetchedResponse = $prefetched->get('https://unused.test/')->toPsrResponse();
        $mappedResponse = $mapped->get(self::DETAIL_URL)->toPsrResponse();

        self::assertSame(202, $prefetchedResponse->getStatusCode());
        self::assertSame(self::DETAIL_URL, $prefetched->finalUrl());
        self::assertSame($payload, (string) $prefetchedResponse->getBody());
        self::assertSame($payload, (string) $mappedResponse->getBody());
        self::assertSame('', (string) $mapped->get('https://unused.test/')->toPsrResponse()->getBody());
    }
}
