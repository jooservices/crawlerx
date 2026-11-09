<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimiter.php';

final class LiveCheckRequestLimiterTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_it_limits_requests_from_followups_in_a_single_crawl(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', '/dyn/phpauto/movie_lists/list_newest_0.json', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'application/json'], $this->fixture('list-newest-0.json')),
        ));
        $registry->respond('GET', '/dyn/phpauto/movie_details/movie_id/060426_001.json', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'application/json'], $this->fixture('detail-060426_001.json')),
        ));

        $now = 0;
        $attemptedUrls = [];
        $permittedAt = [];
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static fn(): int => $now,
        );
        $beforeOutboundRequest = static function (string $url) use ($limiter, &$attemptedUrls, &$permittedAt, &$now): bool {
            $attemptedUrls[] = $url;
            $allowed = $limiter->beforeRequest($url);
            if ($allowed) {
                $permittedAt[] = $now;
            }

            return $allowed;
        };

        CrawlerXFactory::configure(beforeOutboundRequest: $beforeOutboundRequest);
        CrawlerXFactory::useFetchChain(new FetchFallbackChain([
            FetchMethod::Http->value => new HttpFetchHandler(new ClientFactory($beforeOutboundRequest)),
        ]));

        $listing = $limiter->forAdapter('onepondo', static fn() => CrawlerX::url(
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json',
        )->site('onepondo')->type(CrawlType::Listing)->tryCrawl());
        $detail = $limiter->forAdapter('onepondo', static fn() => CrawlerX::url(
            'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json',
        )->site('onepondo')->type(CrawlType::Detail)->tryCrawl());

        self::assertTrue($listing->ok);
        self::assertTrue($detail->ok);
        self::assertCount(3, $attemptedUrls);
        self::assertStringContainsString('/dyn/dla/json/movie_gallery/060426_001.json', $attemptedUrls[2]);
        self::assertCount(2, $registry->recorded());
        self::assertCount(2, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
    }

    public function test_fetch_retries_use_the_same_adapter_budget_and_host_interval(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', '/dyn/phpauto/movie_lists/list_newest_0.json', (new TestResponseSequence())
            ->push(new Response(503, ['Content-Type' => 'text/plain'], 'temporary failure'))
            ->push(new Response(200, ['Content-Type' => 'application/json'], $this->fixture('list-newest-0.json'))));

        $now = 0;
        $permittedAt = [];
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static fn(): int => $now,
        );
        $beforeOutboundRequest = static function (string $url) use ($limiter, &$permittedAt, &$now): bool {
            $allowed = $limiter->beforeRequest($url);
            if ($allowed) {
                $permittedAt[] = $now;
            }

            return $allowed;
        };

        CrawlerXFactory::configure(beforeOutboundRequest: $beforeOutboundRequest);
        CrawlerXFactory::useFetchChain(new FetchFallbackChain(
            [FetchMethod::Http->value => new HttpFetchHandler(new ClientFactory($beforeOutboundRequest))],
            retrySleeper: static function (int $microseconds): void {
            },
        ));

        $outcome = $limiter->forAdapter('onepondo', static fn() => CrawlerX::url(
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json',
        )->site('onepondo')->type(CrawlType::Listing)->tryCrawl());

        self::assertTrue($outcome->ok);
        self::assertCount(2, $registry->recorded());
        self::assertCount(2, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
    }

    private function fixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/../../Fixtures/onepondo/' . $name);
        self::assertIsString($body);

        return $body;
    }
}
