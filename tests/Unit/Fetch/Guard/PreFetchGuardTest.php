<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Guard\PreFetchGuard;
use JOOservices\CrawlerX\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;

final class PreFetchGuardTest extends TestCase
{
    public function test_lets_everything_through_without_guards_or_host(): void
    {
        self::assertNull((new PreFetchGuard())->check('https://a.test/x', $this->profile(), FetchMethod::Http, new FetchBudget(10)));
        self::assertNull((new PreFetchGuard(null, $this->openCircuit('a.test')))->check('not a url', $this->profile(), FetchMethod::Http, new FetchBudget(10)));
    }

    public function test_an_open_circuit_answers_with_a_synthetic_429(): void
    {
        $guard = new PreFetchGuard(null, $this->openCircuit('a.test'));

        $result = $guard->check('https://a.test/x', $this->profile(), FetchMethod::Http, new FetchBudget(10));

        self::assertNotNull($result);
        self::assertFalse($result->ok);
        self::assertSame(429, $result->status);
        self::assertSame(CrawlErrorCode::RateLimited->value, $result->error);
        self::assertSame(['circuit'], $result->headers['X-CrawlerX-Guard']);
        self::assertSame(['300'], $result->headers['Retry-After']);
        self::assertSame('https://a.test/x', $result->finalUrl);
    }

    public function test_the_circuit_is_consulted_once_per_fetch(): void
    {
        $guard = new PreFetchGuard(null, $this->openCircuit('a.test'));
        $budget = new FetchBudget(10);

        self::assertNotNull($guard->check('https://a.test/x', $this->profile(), FetchMethod::Http, $budget));
        self::assertNull($guard->check('https://a.test/x', $this->profile(), FetchMethod::Http, $budget));
    }

    public function test_robots_crawl_delay_raises_the_throttle_gap(): void
    {
        $now = 0.0;
        $throttle = new HostThrottle(
            cache: new ArrayCache(),
            nodeId: 'node-a',
            clock: static function () use (&$now): float {
                return $now;
            },
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1_000_000;
            },
        );
        $guard = new PreFetchGuard($throttle, null, 5.0);
        $profile = new SiteProfileDto(
            slug: 'sample',
            displayName: 'Sample',
            baseUrl: 'https://a.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [],
            http: new HttpProfileDto(),
            defaultThrottle: ['default_gap_seconds' => 1.0, 'min_gap_seconds' => 0.0, 'max_gap_seconds' => 10.0],
        );

        self::assertNull($guard->check('https://a.test/x', $profile, FetchMethod::Http, $this->budget($now, 20)));

        $result = $guard->check('https://a.test/x', $profile, FetchMethod::Http, $this->budget($now, 2));

        self::assertNotNull($result);
        self::assertSame(['5'], $result->headers['Retry-After']);
    }

    private function openCircuit(string $host): HostCircuit
    {
        $circuit = new HostCircuit(cache: new ArrayCache(), clock: static fn(): float => 100.0);
        for ($failure = 0; $failure < 10; $failure++) {
            $circuit->recordFailure($host);
        }

        return $circuit;
    }

    private function budget(float &$now, int $deadlineSeconds): FetchBudget
    {
        return new FetchBudget(
            deadlineSeconds: $deadlineSeconds,
            startedAt: (int) ($now * 1_000_000_000),
            clock: static function () use (&$now): float {
                return $now * 1_000_000_000;
            },
        );
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(slug: 'sample', displayName: 'Sample', baseUrl: 'https://a.test', fetchProfile: FetchProfile::Adaptive, fetchChain: [], http: new HttpProfileDto());
    }
}
