<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature\FetchLab;

use Faker\Factory;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use JOOservices\CrawlerX\Tests\TestCase;

final class SessionStoreFeatureTest extends TestCase
{
    public function test_session_record_is_normalized_and_expires_from_memory(): void
    {
        $faker = Factory::create();
        $now = 10_000;
        $store = new SessionStore(node: 'feature-node', clock: static function () use (&$now): int {
            return $now;
        });
        $token = $faker->sha256();

        $store->put(
            site: 'Demo',
            cookies: ['session' => $token, 'invalid' => 123],
            userAgent: 'Feature-UA',
            source: 'http',
            expiresAt: $now + 600,
            storageState: ['cookies' => [['name' => 'session', 'value' => $token]]],
            challengeCount: -1,
        );

        $record = $store->get('demo');
        self::assertNotNull($record);
        self::assertSame(['session' => $token], $record['cookies']);
        self::assertSame('Feature-UA', $store->userAgent('demo'));
        self::assertSame('session=' . $token, $store->cookieHeader('demo'));

        $now += 600;
        self::assertNull($store->get('demo'));
    }

    public function test_fetch_result_storage_state_is_saved_for_browser_replay(): void
    {
        $faker = Factory::create();
        $token = $faker->sha256();
        $store = new SessionStore(node: 'feature-node');
        $result = new FetchResultDto(
            ok: true,
            body: '<html><body>fixture</body></html>',
            status: 200,
            methodUsed: FetchMethod::Playwright,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: 'https://fixture.test/',
            storageState: [
                'cookies' => [['name' => 'browser_session', 'value' => $token, 'expires' => time() + 60]],
            ],
            userAgent: 'Browser-UA',
        );

        $store->putResult('fixture', $result);

        self::assertSame($token, $store->get('fixture')['cookies']['browser_session']);
        self::assertSame('Browser-UA', $store->userAgent('fixture'));
        self::assertSame($result->storageState, $store->storageState('fixture'));
    }
}
