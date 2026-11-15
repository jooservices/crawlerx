<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleCrawler;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimitException.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimiter.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckSampleCrawler.php';

final class LiveCheckSampleCrawlerTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_optional_followup_stays_within_budget_and_keeps_both_samples_checked(): void
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
            static function () use (&$now): int {
                return $now;
            },
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

        $crawler = new LiveCheckSampleCrawler($limiter);
        $runner = new LiveCheckReportRunner(
            crawl: static fn(array $sample) => $crawler->crawl($sample),
            sleepMicroseconds: static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            clockNanoseconds: static function () use (&$now): int {
                return $now;
            },
        );
        $results = $runner->run([
            $this->sample(CrawlType::Listing, 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json'),
            $this->sample(CrawlType::Detail, 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json'),
        ]);

        self::assertSame(['ok', 'ok'], array_column($results, 'status'));
        self::assertSame([
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json',
            'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json',
            'https://en.1pondo.tv/dyn/dla/json/movie_gallery/060426_001.json',
        ], $attemptedUrls);
        self::assertCount(3, $registry->recorded());
        self::assertCount(3, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[2] - $permittedAt[1]);
    }

    /** @return array{slug: string, adapter_name: string, role: string, name: string, type: CrawlType, url: string, fallback_required: bool} */
    private function sample(CrawlType $type, string $url): array
    {
        return [
            'slug' => 'onepondo',
            'adapter_name' => 'OnePondo',
            'role' => $type === CrawlType::Listing ? 'listing' : 'detail',
            'name' => $type->value,
            'type' => $type,
            'url' => $url,
            'fallback_required' => false,
        ];
    }

    private function fixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/../../Fixtures/onepondo/' . $name);
        self::assertIsString($body);

        return $body;
    }
}
