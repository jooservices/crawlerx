<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use Closure;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class HostThrottle
{
    /** @var array<string, float> */
    private array $nextAllowedAt = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    /** @var Closure(int): void */
    private readonly Closure $sleeper;

    /**
     * @param  Closure(): float|null  $clock
     * @param  Closure(int): void|null  $sleeper
     */
    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly string $nodeId = 'local',
        ?Closure $clock = null,
        ?Closure $sleeper = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /**
     * Waits for the next slot or returns the retry delay when the wait cannot
     * fit inside the request budget. Cache reservation is best effort because
     * PSR-16 does not define an atomic add operation.
     *
     * @param  array<string, float>|null  $settings
     */
    public function waitBeforeFetch(
        string $host,
        ?array $settings,
        FetchBudget $budget,
        FetchMethod $method,
    ): ?int {
        $gap = $this->gapSeconds($settings);
        $host = strtolower(rtrim(trim($host), '.'));
        if ($gap <= 0.0 || $host === '') {
            return null;
        }

        $now = ($this->clock)();
        $key = $this->key($host);
        $nextAllowedAt = $this->readReservation($key, $now);
        if ($nextAllowedAt === false) {
            return 1;
        }

        $startAt = max($now, $nextAllowedAt ?? $now);
        $waitSeconds = max(0.0, $startAt - $now);
        if ($waitSeconds > 0.0 && $waitSeconds + 1.0 > $budget->remainingSeconds()) {
            return max(1, (int) ceil($waitSeconds));
        }

        $nextSlot = $startAt + $gap;
        if (! $this->reserve($key, $nextSlot, $now)) {
            return 1;
        }

        if ($waitSeconds > 0.0) {
            ($this->sleeper)((int) round($waitSeconds * 1_000_000));
        }

        if (! $budget->canStart($method)) {
            return max(1, (int) ceil(max(0.0, $nextSlot - ($this->clock)())));
        }

        return null;
    }

    /** @param array<string, float>|null $settings */
    private function gapSeconds(?array $settings): float
    {
        if ($settings === null) {
            return 0.0;
        }

        $default = $settings['default_gap_seconds'] ?? 0.0;
        $minimum = $settings['min_gap_seconds'] ?? 0.0;
        $maximum = $settings['max_gap_seconds'] ?? max($minimum, $default);
        if (! is_finite($default) || ! is_finite($minimum) || ! is_finite($maximum)) {
            return 0.0;
        }

        $minimum = max(0.0, $minimum);
        $maximum = max($minimum, $maximum);

        return min($maximum, max($minimum, $default));
    }

    /**
     * @return float|null|false false means the cache value is invalid/unavailable
     */
    private function readReservation(string $key, float $now): float|false|null
    {
        if ($this->cache === null) {
            $timestamp = $this->nextAllowedAt[$key] ?? null;
            if ($timestamp !== null && $timestamp <= $now) {
                unset($this->nextAllowedAt[$key]);
            }

            return $timestamp !== null && $timestamp > $now ? $timestamp : null;
        }

        try {
            $timestamp = $this->cache->get($key);
        } catch (Throwable) {
            return false;
        }

        if ($timestamp === null) {
            return null;
        }

        if ((! is_float($timestamp) && ! is_int($timestamp)) || ! is_finite((float) $timestamp)) {
            return false;
        }

        $timestamp = (float) $timestamp;

        return $timestamp > $now ? $timestamp : null;
    }

    private function reserve(string $key, float $nextSlot, float $now): bool
    {
        if ($this->cache === null) {
            $this->nextAllowedAt[$key] = $nextSlot;

            return true;
        }

        $ttl = max(1, (int) ceil($nextSlot - $now) + 1);
        try {
            return $this->cache->set($key, $nextSlot, $ttl);
        } catch (Throwable) {
            return false;
        }
    }

    private function key(string $host): string
    {
        // PSR-16 reserves punctuation including ':' and limits keys to 64 chars.
        return 'crawlerx.throttle.' . substr(hash('sha256', $host . "\0" . $this->nodeId), 0, 46);
    }
}
