<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use Closure;

final class LiveCheckRequestLimiter
{
    private const MAX_REQUESTS_PER_ADAPTER = 2;

    private const MIN_HOST_INTERVAL_NANOSECONDS = 10_000_000_000;

    private ?string $adapterSlug = null;

    /** @var array<string, int> */
    private array $requestCountByAdapter = [];

    /** @var array<string, int> */
    private array $lastRequestByHost = [];

    /**
     * @param Closure(int): void $sleepMicroseconds
     * @param Closure(): int $clockNanoseconds
     */
    public function __construct(
        private readonly Closure $sleepMicroseconds,
        private readonly Closure $clockNanoseconds,
    ) {
    }

    /** @template T @param Closure(): T $operation @return T */
    public function forAdapter(string $slug, Closure $operation): mixed
    {
        $previousSlug = $this->adapterSlug;
        $this->adapterSlug = $slug;

        try {
            return $operation();
        } finally {
            $this->adapterSlug = $previousSlug;
        }
    }

    /** Returns false before sending when this adapter has exhausted its outbound request budget. */
    public function beforeRequest(string $url): bool
    {
        $slug = $this->adapterSlug;
        $host = parse_url($url, PHP_URL_HOST);
        if ($slug === null || ! is_string($host) || $host === '') {
            return true;
        }

        if (($this->requestCountByAdapter[$slug] ?? 0) >= self::MAX_REQUESTS_PER_ADAPTER) {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));
        $this->waitForHost($host);
        $this->requestCountByAdapter[$slug] = ($this->requestCountByAdapter[$slug] ?? 0) + 1;
        $this->lastRequestByHost[$host] = ($this->clockNanoseconds)();

        return true;
    }

    private function waitForHost(string $host): void
    {
        if (! isset($this->lastRequestByHost[$host])) {
            return;
        }

        $elapsed = ($this->clockNanoseconds)() - $this->lastRequestByHost[$host];
        $remaining = self::MIN_HOST_INTERVAL_NANOSECONDS - $elapsed;
        if ($remaining > 0) {
            ($this->sleepMicroseconds)((int) ceil($remaining / 1_000));
        }
    }
}
