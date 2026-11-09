<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use PHPUnit\Framework\TestCase;

final class HostThrottleElapsedTest extends TestCase
{
    public function test_wait_only_covers_the_gap_remaining_after_the_previous_request(): void
    {
        $now = 0.0;
        $slept = 0.0;
        $throttle = new HostThrottle(
            clock: static function () use (&$now): float {
                return $now;
            },
            sleeper: static function (int $microseconds) use (&$now, &$slept): void {
                $seconds = $microseconds / 1_000_000;
                $slept += $seconds;
                $now += $seconds;
            },
        );
        $settings = ['default_gap_seconds' => 10.0, 'min_gap_seconds' => 10.0, 'max_gap_seconds' => 10.0];

        self::assertNull($throttle->waitBeforeFetch('example.test', $settings, $this->budget($now), FetchMethod::Http));
        $now += 7.0;
        self::assertNull($throttle->waitBeforeFetch('example.test', $settings, $this->budget($now), FetchMethod::Http));

        self::assertSame(3.0, $slept);
    }

    public function test_wait_is_skipped_when_the_previous_request_already_spent_the_gap(): void
    {
        $now = 0.0;
        $slept = 0.0;
        $throttle = new HostThrottle(
            clock: static function () use (&$now): float {
                return $now;
            },
            sleeper: static function (int $microseconds) use (&$now, &$slept): void {
                $seconds = $microseconds / 1_000_000;
                $slept += $seconds;
                $now += $seconds;
            },
        );
        $settings = ['default_gap_seconds' => 5.0, 'min_gap_seconds' => 5.0, 'max_gap_seconds' => 5.0];

        self::assertNull($throttle->waitBeforeFetch('example.test', $settings, $this->budget($now), FetchMethod::Http));
        $now += 6.0;
        self::assertNull($throttle->waitBeforeFetch('example.test', $settings, $this->budget($now), FetchMethod::Http));

        self::assertSame(0.0, $slept);
    }

    private function budget(float &$now): FetchBudget
    {
        return new FetchBudget(
            deadlineSeconds: 30,
            startedAt: $now * 1_000_000_000,
            clock: static function () use (&$now): float {
                return $now * 1_000_000_000;
            },
        );
    }
}
