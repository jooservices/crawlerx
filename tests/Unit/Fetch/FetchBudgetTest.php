<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FetchBudgetTest extends TestCase
{
    public function test_default_and_consumer_deadlines_are_capped_at_sixty_seconds(): void
    {
        self::assertSame(60, FetchBudget::start(null)->deadlineSeconds());
        self::assertSame(60, FetchBudget::start(new FetchOptionsDto(deadlineSeconds: 300))->deadlineSeconds());
        self::assertSame(7, FetchBudget::start(new FetchOptionsDto(deadlineSeconds: 7))->deadlineSeconds());
    }

    /** @return iterable<string, array{?int, list<int>, list<string>, int}> */
    public static function deadlines(): iterable
    {
        yield 'default' => [null, [20, 40], ['http', 'playwright', 'flaresolverr'], 60_000];
        yield 'consumer deadline' => [7, [7], ['http', 'playwright'], 7_000];
    }

    /** @param list<int> $expectedTimeouts
     * @param list<string> $expectedMethods
     */
    #[DataProvider('deadlines')]
    public function test_fallbacks_share_one_deadline_and_keep_all_attempts(?int $deadline, array $expectedTimeouts, array $expectedMethods, int $elapsedMs): void
    {
        $now = 0;
        $timeouts = [];
        $handler = new class ($now, $timeouts) implements FetchMethodHandler {
            /** @param list<int|null> $timeouts */
            public function __construct(private int &$now, private array &$timeouts)
            {
            }

            public function supports(FetchMethod $method): bool
            {
                return true;
            }

            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                $this->timeouts[] = $options?->methodTimeoutSeconds;
                $this->now += ($options?->methodTimeoutSeconds ?? 0) * 1_000_000_000;

                return new FetchResultDto(ok: false, body: '', status: 0, methodUsed: $method, elapsedMs: 0, challengeDetected: false, error: 'synthetic timeout');
            }
        };
        $chain = new FetchFallbackChain([
            'http' => $handler, 'playwright' => $handler, 'flaresolverr' => $handler,
        ], maxTransientRetries: 0, budgetClock: static function () use (&$now): int {
            return $now;
        });
        $profile = new SiteProfileDto(slug: 'budget', displayName: 'Budget', baseUrl: 'https://example.test', fetchProfile: FetchProfile::Adaptive, fetchChain: [], http: new HttpProfileDto());
        try {
            $chain->fetch('https://example.test', $profile, [FetchMethod::Http, FetchMethod::Playwright, FetchMethod::Flaresolverr], new CrawlOptionsDto(fetch: new FetchOptionsDto(deadlineSeconds: $deadline)));
            self::fail('Expected the shared deadline to stop fallback.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Timeout, $exception->errorCode);
            self::assertSame($expectedTimeouts, $timeouts);
            self::assertSame($elapsedMs, $exception->fetch?->elapsedMs);
            self::assertSame($expectedMethods, array_column($exception->fetch?->attempts ?? [], 'method'));
            self::assertSame('fetch budget exhausted', $exception->fetch?->attempts[count($expectedMethods) - 1]['error']);
        }
    }

    public function test_exceptions_after_a_result_are_retained_in_failure_metadata(): void
    {
        $handler = new class implements FetchMethodHandler {
            public function supports(FetchMethod $method): bool
            {
                return true;
            }
            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                if ($method === FetchMethod::Playwright) {
                    throw new \RuntimeException('synthetic failure');
                }
                return new FetchResultDto(ok: false, body: '', status: 0, methodUsed: $method, elapsedMs: 1, challengeDetected: false);
            }
        };
        $chain = new FetchFallbackChain(['http' => $handler, 'playwright' => $handler], maxTransientRetries: 0);
        $profile = new SiteProfileDto(slug: 'budget', displayName: 'Budget', baseUrl: 'https://example.test', fetchProfile: FetchProfile::Adaptive, fetchChain: [], http: new HttpProfileDto());
        try {
            $chain->fetch('https://example.test', $profile, [FetchMethod::Http, FetchMethod::Playwright]);
            self::fail('Expected failure.');
        } catch (CrawlFetchException $exception) {
            self::assertCount(2, $exception->fetch?->attempts ?? []);
            self::assertSame('playwright', $exception->fetch?->attempts[1]['method']);
        }
    }
}
