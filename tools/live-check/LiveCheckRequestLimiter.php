<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use Closure;

final class LiveCheckRequestLimiter
{
    private const MAX_CONTENT_REQUESTS_PER_ADAPTER = 2;

    private const MIN_HOST_INTERVAL_NANOSECONDS = 10_000_000_000;

    private ?string $adapterSlug = null;

    /** @var array<string, int> */
    private array $requestCountByAdapter = [];

    /** @var array<string, int> */
    private array $lastRequestByHost = [];

    /** @var array<string, bool> */
    private array $budgetExceededByAdapter = [];

    /** @var array<string, bool> */
    private array $unsupportedRedirectByAdapter = [];

    /**
     * @param Closure(int): void $sleepMicroseconds
     * @param Closure(): int $clockNanoseconds
     */
    public function __construct(
        private readonly Closure $sleepMicroseconds,
        private readonly Closure $clockNanoseconds,
    ) {
    }

    /** Runs one sample crawl under the selected adapter's shared content request budget.
     *
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function forAdapter(string $slug, Closure $operation): mixed
    {
        $previousSlug = $this->adapterSlug;
        if ($previousSlug !== $slug) {
            unset($this->unsupportedRedirectByAdapter[$slug]);
        }
        $this->adapterSlug = $slug;

        try {
            return $operation();
        } finally {
            $this->adapterSlug = $previousSlug;
        }
    }

    /** Returns false before sending when this adapter has exhausted its content request budget. */
    public function beforeRequest(string $url): bool
    {
        return $this->permit($url, countForAdapter: true);
    }

    /** Paces the shared robots lookup without consuming a sample's content request allowance. */
    public function beforeRobotsRequest(string $url): bool
    {
        return $this->permit($url, countForAdapter: false);
    }

    /** Paces the hostname and optionally counts the request against its adapter.
     */
    private function permit(string $url, bool $countForAdapter): bool
    {
        $slug = $this->adapterSlug;
        if ($countForAdapter && $slug !== null
            && ($this->requestCountByAdapter[$slug] ?? 0) >= self::MAX_CONTENT_REQUESTS_PER_ADAPTER
        ) {
            $this->budgetExceededByAdapter[$slug] = true;

            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));
        $this->waitForHost($host);
        if ($countForAdapter && $slug !== null) {
            $this->requestCountByAdapter[$slug] = ($this->requestCountByAdapter[$slug] ?? 0) + 1;
        }
        $this->lastRequestByHost[$host] = ($this->clockNanoseconds)();

        return true;
    }

    public function wasBudgetExceeded(string $slug): bool
    {
        return $this->budgetExceededByAdapter[$slug] ?? false;
    }

    public function markUnsupportedRedirect(): void
    {
        if ($this->adapterSlug !== null) {
            $this->unsupportedRedirectByAdapter[$this->adapterSlug] = true;
        }
    }

    public function wasRedirectUnsupported(string $slug): bool
    {
        return $this->unsupportedRedirectByAdapter[$slug] ?? false;
    }

    /** Sleeps until the hostname's previous outbound request is ten seconds old. */
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
