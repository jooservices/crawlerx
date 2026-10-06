<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use Faker\Factory;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;

final class HostThrottleTest extends TestCase
{
    public function test_separate_workers_reserve_host_slots_for_the_same_node(): void
    {
        $now = 0.0;
        $host = Factory::create()->unique()->domainName();
        $settings = ['default_gap_seconds' => 2.0, 'min_gap_seconds' => 2.0, 'max_gap_seconds' => 2.0];
        $cache = new ArrayCache();
        $firstWorker = $this->throttle($cache, 'node-a', $now);
        $secondWorker = $this->throttle($cache, 'node-a', $now);

        self::assertNull($firstWorker->waitBeforeFetch($host, $settings, $this->budget($now, 10), FetchMethod::Http));
        $firstStartedAt = $now;
        self::assertNull($secondWorker->waitBeforeFetch($host, $settings, $this->budget($now, 10), FetchMethod::Http));
        $secondStartedAt = $now;

        self::assertGreaterThanOrEqual(2.0, $secondStartedAt - $firstStartedAt);
    }

    public function test_wait_over_budget_returns_retry_after_without_sleeping(): void
    {
        $now = 0.0;
        $host = Factory::create()->unique()->domainName();
        $cache = new ArrayCache();
        $throttle = $this->throttle($cache, 'node-a', $now);
        $settings = ['default_gap_seconds' => 10.0, 'min_gap_seconds' => 5.0, 'max_gap_seconds' => 60.0];

        self::assertNull($throttle->waitBeforeFetch($host, $settings, $this->budget($now, 20), FetchMethod::Http));
        self::assertSame(10, $throttle->waitBeforeFetch($host, $settings, $this->budget($now, 2), FetchMethod::Http));
        self::assertSame(0.0, $now);
    }

    public function test_gap_is_clamped_to_manifest_minimum_and_maximum(): void
    {
        $now = 0.0;
        $host = Factory::create()->unique()->domainName();
        $cache = new ArrayCache();
        $throttle = $this->throttle($cache, 'node-a', $now);

        self::assertNull($throttle->waitBeforeFetch(
            $host,
            ['default_gap_seconds' => 100.0, 'min_gap_seconds' => 2.0, 'max_gap_seconds' => 4.0],
            $this->budget($now, 20),
            FetchMethod::Http,
        ));
        self::assertSame(4, $throttle->waitBeforeFetch(
            $host,
            ['default_gap_seconds' => 100.0, 'min_gap_seconds' => 2.0, 'max_gap_seconds' => 4.0],
            $this->budget($now, 4),
            FetchMethod::Http,
        ));
    }

    public function test_host_and_node_are_isolated_in_shared_cache(): void
    {
        $now = 0.0;
        $host = Factory::create()->unique()->domainName();
        $settings = ['default_gap_seconds' => 10.0, 'min_gap_seconds' => 5.0, 'max_gap_seconds' => 60.0];
        $cache = new ArrayCache();
        $nodeA = $this->throttle($cache, 'node-a', $now);
        $nodeB = $this->throttle($cache, 'node-b', $now);

        self::assertNull($nodeA->waitBeforeFetch($host, $settings, $this->budget($now, 20), FetchMethod::Http));
        self::assertNull($nodeB->waitBeforeFetch($host, $settings, $this->budget($now, 20), FetchMethod::Http));
        self::assertNull($nodeA->waitBeforeFetch(
            Factory::create()->unique()->domainName(),
            $settings,
            $this->budget($now, 20),
            FetchMethod::Http,
        ));
    }

    private function throttle(ArrayCache $cache, string $node, float &$now): HostThrottle
    {
        return new HostThrottle(
            cache: $cache,
            nodeId: $node,
            clock: static function () use (&$now): float {
                return $now;
            },
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1_000_000;
            },
        );
    }

    private function budget(float &$now, int $deadlineSeconds): FetchBudget
    {
        return new FetchBudget(
            deadlineSeconds: $deadlineSeconds,
            startedAt: $now * 1_000_000_000,
            clock: static function () use (&$now): float {
                return $now * 1_000_000_000;
            },
        );
    }
}
