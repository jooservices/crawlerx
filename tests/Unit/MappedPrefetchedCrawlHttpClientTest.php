<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Http\MappedPrefetchedCrawlHttpClient;
use PHPUnit\Framework\TestCase;

final class MappedPrefetchedCrawlHttpClientTest extends TestCase
{
    public function test_returns_configured_response_and_default_response(): void
    {
        $client = new MappedPrefetchedCrawlHttpClient(
            responses: [
                'https://example.test/found' => [
                    'body' => '{"found":true}',
                    'status' => 201,
                    'headers' => ['X-Source' => 'fixture'],
                ],
            ],
            defaultBody: '{"found":false}',
        );

        $found = $client->get('https://example.test/found')->toPsrResponse();
        $missing = $client->get('https://example.test/missing')->toPsrResponse();

        self::assertSame(201, $found->getStatusCode());
        self::assertSame('fixture', $found->getHeaderLine('X-Source'));
        self::assertSame('{"found":true}', (string) $found->getBody());
        self::assertSame(404, $missing->getStatusCode());
        self::assertSame('application/json', $missing->getHeaderLine('Content-Type'));
        self::assertSame('{"found":false}', (string) $missing->getBody());
    }
}
