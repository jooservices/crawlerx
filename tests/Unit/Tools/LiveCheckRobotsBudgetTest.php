<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckClassifier;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimitException;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimitException.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimiter.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';

final class LiveCheckRobotsBudgetTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        ClientBuilder::clearFake();
        parent::tearDown();
    }

    public function test_the_robots_lookup_does_not_consume_either_sample_request(): void
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

        $crawl = static function (array $sample) use ($limiter): CrawlOutcomeDto {
            try {
                $outcome = $limiter->forAdapter(
                    $sample['slug'],
                    static fn(): CrawlOutcomeDto => CrawlerX::url($sample['url'])
                        ->site($sample['slug'])
                        ->type($sample['type'])
                        ->options(new \JOOservices\CrawlerX\Dto\CrawlOptionsDto(
                            fetch: new FetchOptionsDto(profile: FetchProfile::HttpOnly),
                        ))
                        ->tryCrawl(),
                );
            } catch (\Throwable $exception) {
                if ($limiter->wasBudgetExceeded($sample['slug'])) {
                    throw new LiveCheckRequestLimitException(previous: $exception);
                }

                throw $exception;
            }

            if ($limiter->wasBudgetExceeded($sample['slug'])) {
                throw new LiveCheckRequestLimitException();
            }

            return $outcome;
        };
        $runner = new LiveCheckReportRunner(
            crawl: $crawl,
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
}
