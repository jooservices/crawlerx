<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Session;

use DateInterval;
use Faker\Factory;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class SessionStoreTest extends TestCase
{
    public function test_tc_se_03_shared_psr16_cache_reuses_a_session(): void
    {
        $faker = Factory::create();
        $cache = new ArrayCache();
        $first = new SessionStore($cache, 'node-a');
        $second = new SessionStore($cache, 'node-a');
        $token = $faker->sha256();

        $first->put('demo', ['cf_clearance' => $token], 'ua-1', 'flaresolverr');

        self::assertSame($token, $second->get('demo')['cookies']['cf_clearance']);
        self::assertSame('ua-1', $second->userAgent('demo'));
    }

    public function test_tc_se_04_ttl_is_capped_and_expires_with_a_fake_clock(): void
    {
        $now = 10_000;
        $store = new SessionStore(node: 'node-a', clock: static function () use (&$now): int {
            return $now;
        });

        $store->put('demo', ['session' => 'value'], 'ua-1', 'http', $now + 3600);
        self::assertSame($now + 1800, $store->get('demo')['expiresAt']);

        $now += 1800;
        self::assertNull($store->get('demo'));
    }

    public function test_tc_se_05_node_is_part_of_the_session_key(): void
    {
        $cache = new ArrayCache();
        $nodeA = new SessionStore($cache, 'node-a');
        $nodeB = new SessionStore($cache, 'node-b');

        $nodeA->put('demo', ['session' => 'a'], 'ua-a', 'http');

        self::assertSame('a', $nodeA->get('demo')['cookies']['session']);
        self::assertNull($nodeB->get('demo'));
        self::assertSame('crawlerx:session:demo:node-a', $nodeA->key('demo'));
    }

    public function test_tc_se_08_switches_to_the_next_sticky_user_agent_after_three_challenges(): void
    {
        $store = new SessionStore(node: 'node-a');
        $pool = ['ua-1', 'ua-2'];

        $store->recordChallenge('demo', 'ua-1', $pool);
        $store->recordChallenge('demo', 'ua-1', $pool);
        self::assertSame('ua-2', $store->recordChallenge('demo', 'ua-1', $pool));
        self::assertSame('ua-2', $store->userAgent('demo'));
        self::assertSame('ua-2', $store->recordChallenge('demo', 'ua-1', $pool));
    }

    public function test_tc_se_09_session_values_are_not_in_fetch_metadata(): void
    {
        $faker = Factory::create();
        $secret = $faker->sha256();
        $store = new SessionStore(node: 'node-a');
        $store->put('demo', ['remember_token' => $secret], 'ua-1', 'login');
        $result = new FetchResultDto(
            ok: false,
            body: '<html><body>challenge</body></html>',
            status: 403,
            methodUsed: FetchMethod::Http,
            elapsedMs: 1,
            challengeDetected: true,
            finalUrl: 'https://demo.test',
            error: 'challenge',
        );

        $metadata = json_encode($result->withAttempts([[
            'method' => 'http',
            'elapsed_ms' => 1,
            'status' => 403,
            'challenge' => true,
            'ok' => false,
            'error' => 'challenge',
        ]]), JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString($secret, $metadata);
        self::assertSame($secret, $store->get('demo')['cookies']['remember_token']);
    }

    public function test_tc_se_10_without_a_cache_uses_in_memory_storage(): void
    {
        $store = new SessionStore(node: 'node-a');
        $store->put('demo', ['session' => 'value'], null, 'http');

        self::assertSame('value', $store->get('demo')['cookies']['session']);
        $store->forget('demo');
        self::assertNull($store->get('demo'));
    }
}

final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}
