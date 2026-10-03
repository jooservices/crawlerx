<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Session;

use Closure;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use Psr\SimpleCache\CacheInterface;

final class SessionStore
{
    private const MAX_TTL_SECONDS = 1800;

    /** @var array<string, array{record: array<string, mixed>, expires_at: int}> */
    private array $memory = [];

    /** @var Closure(): int */
    private readonly Closure $clock;

    /** @param Closure(): int|null $clock */
    public function __construct(
        private readonly ?CacheInterface $cache = null,
        ?string $node = null,
        ?Closure $clock = null,
    ) {
        if ($node !== null && trim($node) !== '') {
            $this->node = trim($node);
        } else {
            $hostname = gethostname();
            $this->node = is_string($hostname) && $hostname !== '' ? $hostname : 'unknown';
        }
        $this->clock = $clock ?? static fn(): int => time();
    }

    private readonly string $node;

    public function key(string $site): string
    {
        return 'crawlerx:session:' . strtolower(trim($site)) . ':' . $this->node;
    }

    /** @return array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}|null */
    public function get(string $site): ?array
    {
        $key = $this->key($site);
        $value = $this->cache?->get($key);
        if ($this->cache === null) {
            $value = $this->memory[$key]['record'] ?? null;
            if ($value !== null && $this->memory[$key]['expires_at'] <= $this->now()) {
                unset($this->memory[$key]);
                $value = null;
            }
        }

        if (! is_array($value) || ! is_array($value['cookies'] ?? null) || ! is_string($value['source'] ?? null)) {
            return null;
        }

        $expiresAt = is_numeric($value['expiresAt'] ?? null) ? (int) $value['expiresAt'] : 0;
        if ($expiresAt <= $this->now()) {
            $this->forget($site);

            return null;
        }

        /** @var array<string, string> $cookies */
        $cookies = array_filter(
            $value['cookies'],
            static fn(mixed $cookie, mixed $name): bool => is_string($name) && is_string($cookie),
            ARRAY_FILTER_USE_BOTH,
        );
        /** @var array<string, mixed>|null $storageState */
        $storageState = is_array($value['storageState'] ?? null) ? $value['storageState'] : null;

        return [
            'cookies' => $cookies,
            'userAgent' => is_string($value['userAgent'] ?? null) ? $value['userAgent'] : null,
            'source' => $value['source'],
            'expiresAt' => $expiresAt,
            'storageState' => $storageState,
            'challengeCount' => is_numeric($value['challengeCount'] ?? null) ? (int) $value['challengeCount'] : 0,
        ];
    }

    /**
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>|null  $storageState
     */
    public function put(
        string $site,
        array $cookies,
        ?string $userAgent,
        string $source,
        ?int $expiresAt = null,
        ?array $storageState = null,
        int $challengeCount = 0,
    ): void {
        $now = $this->now();
        $expiresAt = $expiresAt === null
            ? $now + self::MAX_TTL_SECONDS
            : min($expiresAt, $now + self::MAX_TTL_SECONDS);
        if ($expiresAt <= $now) {
            $this->forget($site);

            return;
        }

        $record = [
            'cookies' => $cookies,
            'userAgent' => $userAgent,
            'source' => $source,
            'expiresAt' => $expiresAt,
            'storageState' => $storageState,
            'challengeCount' => max(0, $challengeCount),
        ];
        $ttl = max(1, $expiresAt - $now);
        $key = $this->key($site);

        if ($this->cache !== null && $this->cache->set($key, $record, $ttl)) {
            return;
        }

        $this->memory[$key] = ['record' => $record, 'expires_at' => $expiresAt];
    }

    public function putResult(string $site, FetchResultDto $result): void
    {
        $cookies = $result->cookies;
        $storageState = $result->storageState;
        if ($storageState !== null && is_array($storageState['cookies'] ?? null)) {
            foreach ($storageState['cookies'] as $cookie) {
                if (! is_array($cookie) || ! is_string($cookie['name'] ?? null) || ! is_string($cookie['value'] ?? null)) {
                    continue;
                }

                $cookies[$cookie['name']] = $cookie['value'];
            }
        }

        if ($cookies === [] && $storageState === null && $result->userAgent === null) {
            return;
        }

        $this->put(
            site: $site,
            cookies: $cookies,
            userAgent: $result->userAgent,
            source: $result->methodUsed->value,
            expiresAt: $this->storageExpiry($storageState),
            storageState: $storageState,
        );
    }

    public function forget(string $site): void
    {
        $key = $this->key($site);
        $this->cache?->delete($key);
        unset($this->memory[$key]);
    }

    /**
     * Record one completed challenged fetch and return the sticky UA for the
     * next request. The first three challenges keep the current UA; the fourth
     * request uses the next configured UA.
     *
     * @param  list<string>  $pool
     */
    public function recordChallenge(string $site, ?string $currentUserAgent, array $pool, int $threshold = 3): ?string
    {
        $record = $this->get($site);
        $count = ($record['challengeCount'] ?? 0) + 1;
        $current = $record['userAgent'] ?? $currentUserAgent;
        if ($record === null) {
            $record = [
                'cookies' => [],
                'userAgent' => $current,
                'source' => 'ua',
                'expiresAt' => $this->now() + self::MAX_TTL_SECONDS,
                'storageState' => null,
                'challengeCount' => 0,
            ];
        }

        if ($count >= max(1, $threshold) && count($pool) > 1) {
            $index = array_search($current, $pool, true);
            $next = $pool[$index === false ? 0 : (($index + 1) % count($pool))];
            $current = $next;
            $count = 0;
        }

        $this->put(
            site: $site,
            cookies: $record['cookies'],
            userAgent: $current,
            source: $record['source'],
            expiresAt: $record['expiresAt'],
            storageState: $record['storageState'],
            challengeCount: $count,
        );

        return $current;
    }

    /**
     * @param  array<string, string>  $additional
     * @return array<string, string>
     */
    public function cookies(string $site, array $additional = []): array
    {
        $record = $this->get($site);
        /** @var array<string, string> $cookies */
        $cookies = $record['cookies'] ?? [];

        return array_merge($cookies, $additional);
    }

    /** @param array<string, string> $additional */
    public function cookieHeader(string $site, array $additional = []): ?string
    {
        $cookies = $this->cookies($site, $additional);
        if ($cookies === []) {
            return null;
        }

        $parts = [];
        foreach ($cookies as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('; ', $parts);
    }

    public function userAgent(string $site): ?string
    {
        return $this->get($site)['userAgent'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function storageState(string $site): ?array
    {
        return $this->get($site)['storageState'] ?? null;
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    /** @param array<string, mixed>|null $storageState */
    private function storageExpiry(?array $storageState): ?int
    {
        if (! is_array($storageState['cookies'] ?? null)) {
            return null;
        }

        $expiry = null;
        foreach ($storageState['cookies'] as $cookie) {
            $rawExpiry = is_array($cookie) ? ($cookie['expires'] ?? null) : null;
            if (! is_int($rawExpiry) && ! is_float($rawExpiry) && ! (is_string($rawExpiry) && is_numeric($rawExpiry))) {
                continue;
            }

            $candidate = (int) $rawExpiry;
            if ($candidate <= 0 || ($expiry !== null && $candidate >= $expiry)) {
                continue;
            }
            $expiry = $candidate;
        }

        return $expiry;
    }
}
