<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class HostCircuit
{
    private const FAILURE_THRESHOLD = 10;

    private const OPEN_SECONDS = 300;

    private const PROBE_LEASE_SECONDS = 30;

    private const STATE_TTL_SECONDS = 330;

    /** @var array<string, array{failures: int, open_until: float, probe_until: float}> */
    private array $localStates = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    /**
     * @param  Closure(): float|null  $clock
     */
    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly string $nodeId = 'local',
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    /**
     * Returns the retry delay when a host is open or another half-open probe
     * owns the lease. PSR-16 has no atomic reservation, so simultaneous workers
     * can rarely admit more than one probe.
     */
    public function beforeFetch(string $host): ?int
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return null;
        }

        $now = ($this->clock)();
        $key = $this->key($host);
        $state = $this->read($key);

        if ($state['open_until'] <= 0.0) {
            return null;
        }

        if ($state['open_until'] > $now) {
            return $this->retryAfter($state['open_until'], $now);
        }

        if ($state['probe_until'] > $now) {
            return $this->retryAfter($state['probe_until'], $now);
        }

        $state['probe_until'] = $now + self::PROBE_LEASE_SECONDS;
        $this->write($key, $state);

        return null;
    }

    public function recordFailure(string $host): void
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return;
        }

        $now = ($this->clock)();
        $key = $this->key($host);
        $state = $this->read($key);
        $halfOpenProbeFailed = $state['open_until'] > 0.0
            && $state['open_until'] <= $now
            && $state['probe_until'] > $now;

        if ($state['open_until'] > $now) {
            return;
        }

        $state['failures'] = $halfOpenProbeFailed
            ? self::FAILURE_THRESHOLD
            : $state['failures'] + 1;
        $state['probe_until'] = 0.0;
        $state['open_until'] = $state['failures'] >= self::FAILURE_THRESHOLD
            ? $now + self::OPEN_SECONDS
            : 0.0;

        $this->write($key, $state);
    }

    public function recordSuccess(string $host): void
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return;
        }

        $key = $this->key($host);
        unset($this->localStates[$key]);

        if ($this->cache === null) {
            return;
        }

        try {
            if (! $this->cache->delete($key)) {
                $this->write($key, $this->emptyState());
            }
        } catch (Throwable) {
            $this->localStates[$key] = $this->emptyState();
        }
    }

    /** @return array{failures: int, open_until: float, probe_until: float} */
    private function read(string $key): array
    {
        if ($this->cache === null) {
            return $this->localStates[$key] ?? $this->emptyState();
        }

        try {
            $value = $this->cache->get($key);
        } catch (Throwable) {
            return $this->localStates[$key] ?? $this->emptyState();
        }

        if (! is_array($value)) {
            return $this->emptyState();
        }

        $failures = $value['failures'] ?? null;
        $openUntil = $value['open_until'] ?? null;
        $probeUntil = $value['probe_until'] ?? null;
        if (
            ! is_int($failures)
            || $failures < 0
            || (! is_int($openUntil) && ! is_float($openUntil))
            || (! is_int($probeUntil) && ! is_float($probeUntil))
            || ! is_finite((float) $openUntil)
            || ! is_finite((float) $probeUntil)
        ) {
            return $this->emptyState();
        }

        return [
            'failures' => $failures,
            'open_until' => (float) $openUntil,
            'probe_until' => (float) $probeUntil,
        ];
    }

    /** @param array{failures: int, open_until: float, probe_until: float} $state */
    private function write(string $key, array $state): void
    {
        if ($this->cache === null) {
            $this->localStates[$key] = $state;

            return;
        }

        try {
            if ($this->cache->set($key, $state, self::STATE_TTL_SECONDS)) {
                unset($this->localStates[$key]);

                return;
            }
        } catch (Throwable) {
            // Keep a worker-local circuit if the shared cache is unavailable.
        }

        $this->localStates[$key] = $state;
    }

    /** @return array{failures: int, open_until: float, probe_until: float} */
    private function emptyState(): array
    {
        return ['failures' => 0, 'open_until' => 0.0, 'probe_until' => 0.0];
    }

    private function retryAfter(float $timestamp, float $now): int
    {
        return max(1, (int) ceil($timestamp - $now));
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host), '.'));
    }

    private function key(string $host): string
    {
        // PSR-16 reserves punctuation including ':' and limits keys to 64 chars.
        return 'crawlerx.circuit.' . substr(hash('sha256', $host . "\0" . $this->nodeId), 0, 46);
    }
}
