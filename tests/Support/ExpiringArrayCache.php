<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

final class ExpiringArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: ?float}> */
    private array $items = [];

    /** @param \Closure(): float $clock */
    public function __construct(private readonly \Closure $clock)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return $default;
        }
        if ($item['expiresAt'] !== null && $item['expiresAt'] <= ($this->clock)()) {
            unset($this->items[$key]);

            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $now = ($this->clock)();
        $expiresAt = match (true) {
            is_int($ttl) => $now + $ttl,
            $ttl instanceof DateInterval => (float) (new DateTimeImmutable('@' . (int) $now))->add($ttl)->getTimestamp(),
            default => null,
        };
        $this->items[$key] = ['value' => $value, 'expiresAt' => $expiresAt];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    /** @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->get($key, $default);
        }

        return $items;
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
