<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckClassifier;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleCrawler;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimiter.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimitException.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckSampleCrawler.php';

final class LiveCheckRobotsBudgetTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_the_robots_lookup_counts_toward_the_three_request_adapter_budget(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', '/robots.txt', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/plain'], "User-agent: *\nAllow: /\n"),
        ));
        $registry->respond('GET', '/en/new', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html'], $this->fixture('listing-new.html')),
        ));
        $registry->respond('GET', '/en/fns-247', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/html'], $this->fixture('detail-fns-247.html')),
        ));

        $now = 0;
        $permittedAt = [];
        $requestedUrls = [];
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static function () use (&$now): int {
                return $now;
            },
        );
        $beforeOutboundRequest = static function (string $url) use ($limiter, &$permittedAt, &$requestedUrls, &$now): bool {
            $requestedUrls[] = $url;
            $allowed = $limiter->beforeRequest($url);
            if ($allowed) {
                $permittedAt[] = $now;
            }

            return $allowed;
        };
        $beforeRobotsRequest = static function (string $url) use ($limiter, &$permittedAt, &$requestedUrls, &$now): bool {
            $requestedUrls[] = $url;
            $allowed = $limiter->beforeRobotsRequest($url);
            if ($allowed) {
                $permittedAt[] = $now;
            }

            return $allowed;
        };
        $clientFactory = new ClientFactory($beforeOutboundRequest);
        $robotsClientFactory = new ClientFactory($beforeRobotsRequest);
        $robotsGuard = new RobotsGuard(
            fetchRobotsTxt: static function (string $url) use ($robotsClientFactory): ?string {
                $response = $robotsClientFactory->factory()->get($url)->toPsrResponse();

                return (string) $response->getBody();
            },
        );

        CrawlerXFactory::configure(
            beforeOutboundRequest: $beforeOutboundRequest,
            beforeRobotsRequest: $beforeRobotsRequest,
        );
        CrawlerXFactory::useFetchChain(new FetchFallbackChain(
            [FetchMethod::Http->value => new HttpFetchHandler($clientFactory)],
            robotsGuard: $robotsGuard,
            beforeOutboundRequest: $beforeOutboundRequest,
        ));

        $crawler = new LiveCheckSampleCrawler($limiter);
        $runner = new LiveCheckReportRunner(
            crawl: static fn(array $sample) => $crawler->crawl($sample),
            sleepMicroseconds: static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            clockNanoseconds: static fn(): int => $now,
            classifier: new LiveCheckClassifier(),
        );

        $results = $runner->run([
            $this->sample(CrawlType::Listing, 'https://missav.ws/en/new'),
            $this->sample(CrawlType::Detail, 'https://missav.ws/en/fns-247'),
        ]);

        self::assertSame(['ok', 'ok'], array_column($results, 'status'));
        self::assertCount(3, $registry->recorded());
        self::assertSame([
            'https://missav.ws/robots.txt',
            'https://missav.ws/en/new',
            'https://missav.ws/en/fns-247',
        ], $requestedUrls);
        self::assertCount(3, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[2] - $permittedAt[1]);
    }

    public function test_a_multi_request_listing_does_not_hide_an_unchecked_detail_sample(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', '/robots.txt', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/plain'], "User-agent: *\nAllow: /\n"),
        ));
        $registry->respond('GET', '/w/api.php', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'application/json'], $this->commonsFixture('listing-root.json')),
        ));

        $now = 0;
        $attemptedUrls = [];
        $limiter = new LiveCheckRequestLimiter(
            static function (int $microseconds) use (&$now): void {
                $now += $microseconds * 1_000;
            },
            static function () use (&$now): int {
                return $now;
            },
        );
        $beforeOutboundRequest = static function (string $url) use ($limiter, &$attemptedUrls): bool {
            $attemptedUrls[] = $url;

            return $limiter->beforeRequest($url);
        };
        $beforeRobotsRequest = static function (string $url) use ($limiter, &$attemptedUrls): bool {
            $attemptedUrls[] = $url;

            return $limiter->beforeRobotsRequest($url);
        };
        $clientFactory = new ClientFactory($beforeOutboundRequest);
        $robotsClientFactory = new ClientFactory($beforeRobotsRequest);
        $robotsGuard = new RobotsGuard(
            fetchRobotsTxt: static function (string $url) use ($robotsClientFactory): ?string {
                return (string) $robotsClientFactory->factory()->get($url)->toPsrResponse()->getBody();
            },
        );

        CrawlerXFactory::configure(
            beforeOutboundRequest: $beforeOutboundRequest,
            beforeRobotsRequest: $beforeRobotsRequest,
        );
        CrawlerXFactory::useFetchChain(new FetchFallbackChain(
            [FetchMethod::Http->value => new HttpFetchHandler($clientFactory)],
            robotsGuard: $robotsGuard,
            beforeOutboundRequest: $beforeOutboundRequest,
        ));

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
            $this->commonsSample(
                'listing',
                CrawlType::Listing,
                'https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=Category%3AGravure+idols+from+Japan&cmtype=subcat%7Cfile&cmlimit=5&cmprop=ids%7Ctitle%7Ctype%7Ctimestamp%7Csortkeyprefix',
            ),
            $this->commonsSample(
                'detail',
                CrawlType::PerformerDetail,
                'https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&titles=Category%3AYuzuki+Aikawa&prop=categoryinfo%7Cpageprops%7Cpageimages%7Cinfo%7Crevisions%7Ccategories%7Clanglinks%7Cextlinks&inprop=url&piprop=original%7Cthumbnail%7Cname&pithumbsize=320&rvprop=content%7Ctimestamp&rvslots=main&cllimit=max&lllimit=max&ellimit=max',
            ),
        ]);

        self::assertSame(['blocked', 'not-checked'], array_column($results, 'status'));
        self::assertSame(['blocked', 'request_limit'], array_column($results, 'error_code'));
        self::assertCount(3, $registry->recorded());
        self::assertCount(4, $attemptedUrls);
        self::assertSame('https://commons.wikimedia.org/robots.txt', $attemptedUrls[0]);
    }

    /** @return array{slug: string, adapter_name: string, role: string, name: string, type: CrawlType, url: string, fallback_required: bool} */
    private function sample(CrawlType $type, string $url): array
    {
        return [
            'slug' => 'missav',
            'adapter_name' => 'MissAV',
            'role' => $type === CrawlType::Listing ? 'listing' : 'detail',
            'name' => $type->value,
            'type' => $type,
            'url' => $url,
            'fallback_required' => true,
        ];
    }

    private function fixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/../../Fixtures/missav/' . $name);
        self::assertIsString($body);

        return $body;
    }

    /** @return array{slug: string, adapter_name: string, role: string, name: string, type: CrawlType, url: string, fallback_required: bool} */
    private function commonsSample(string $role, CrawlType $type, string $url): array
    {
        return [
            'slug' => 'commons',
            'adapter_name' => 'Commons',
            'role' => $role,
            'name' => $type->value,
            'type' => $type,
            'url' => $url,
            'fallback_required' => false,
        ];
    }

    private function commonsFixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/../../Fixtures/commons/' . $name);
        self::assertIsString($body);

        return $body;
    }
}
