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

    public function test_a_sample_stopped_by_the_robots_request_budget_is_not_reported_as_blocked(): void
    {
        $registry = ClientBuilder::fake();
        $registry->respond('GET', '/robots.txt', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'text/plain'], "User-agent: *\nAllow: /\n"),
        ));
        $registry->respond('GET', '/dyn/phpauto/movie_lists/list_newest_0.json', (new TestResponseSequence())->push(
            new Response(200, ['Content-Type' => 'application/json'], $this->fixture('list-newest-0.json')),
        ));

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
        $clientFactory = new ClientFactory($beforeOutboundRequest);
        $robotsGuard = new RobotsGuard(
            fetchRobotsTxt: static function (string $url) use ($clientFactory): ?string {
                $response = $clientFactory->factory()->get($url)->toPsrResponse();

                return (string) $response->getBody();
            },
        );

        CrawlerXFactory::configure(beforeOutboundRequest: $beforeOutboundRequest);
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

            if (! $outcome->ok && $limiter->wasBudgetExceeded($sample['slug'])) {
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
            $this->sample(CrawlType::Listing, 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json'),
            $this->sample(CrawlType::Detail, 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json'),
        ]);

        self::assertSame(['ok', 'not-checked'], array_column($results, 'status'));
        self::assertSame('request_limit', $results[1]['error_code']);
        self::assertCount(2, $registry->recorded());
        self::assertStringEndsWith('/robots.txt', (string) $registry->recorded()[0]->request->getUri());
        self::assertStringContainsString('/movie_lists/list_newest_0.json', (string) $registry->recorded()[1]->request->getUri());
        self::assertCount(2, $permittedAt);
        self::assertGreaterThanOrEqual(10_000_000_000, $permittedAt[1] - $permittedAt[0]);
    }

    /** @return array{slug: string, adapter_name: string, role: string, name: string, type: CrawlType, url: string} */
    private function sample(CrawlType $type, string $url): array
    {
        return [
            'slug' => 'onepondo',
            'adapter_name' => '1Pondo',
            'role' => $type === CrawlType::Listing ? 'listing' : 'detail',
            'name' => $type->value,
            'type' => $type,
            'url' => $url,
        ];
    }

    private function fixture(string $name): string
    {
        $body = file_get_contents(__DIR__ . '/../../Fixtures/onepondo/' . $name);
        self::assertIsString($body);

        return $body;
    }
}
