<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Session;

use Faker\Factory;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class SessionStoreTest extends TestCase
{
    public function test_tc_se_03_sessions_stay_inside_one_store(): void
    {
        $faker = Factory::create();
        $first = new SessionStore();
        $second = new SessionStore();
        $token = $faker->sha256();

        $first->put('demo', ['cf_clearance' => $token], 'ua-1', 'flaresolverr');

        self::assertSame($token, $first->get('demo')['cookies']['cf_clearance']);
        self::assertSame('ua-1', $first->userAgent('demo'));
        self::assertNull($second->get('demo'));
        self::assertSame('crawlerx:session:demo', $first->key('demo'));
    }

    public function test_tc_se_04_ttl_is_capped_and_expires_with_a_fake_clock(): void
    {
        $now = 10_000;
        $store = new SessionStore(clock: static function () use (&$now): int {
            return $now;
        });

        $store->put('demo', ['session' => 'value'], 'ua-1', 'http', $now + 3600);
        self::assertSame($now + 1800, $store->get('demo')['expiresAt']);

        $now += 1800;
        self::assertNull($store->get('demo'));
    }

    public function test_tc_se_05_session_key_has_no_node_segment(): void
    {
        $store = new SessionStore();
        $store->put('Demo', ['session' => 'a'], 'ua-a', 'http');

        self::assertSame('a', $store->get('demo')['cookies']['session']);
        self::assertSame('crawlerx:session:demo', $store->key('Demo'));
    }

    public function test_tc_se_08_switches_to_the_next_sticky_user_agent_after_three_challenges(): void
    {
        $store = new SessionStore();
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
        $store = new SessionStore();
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
        $store = new SessionStore();
        $store->put('demo', ['session' => 'value'], null, 'http');

        self::assertSame('value', $store->get('demo')['cookies']['session']);
        $store->forget('demo');
        self::assertNull($store->get('demo'));
    }
}
