<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Tests\Support\ArrayCache;
use PHPUnit\Framework\TestCase;

final class RobotsGuardTest extends TestCase
{
    public function test_robots_txt_is_fetched_once_per_host(): void
    {
        $fetches = 0;
        $guard = $this->guard(
            static function (string $url) use (&$fetches): ?string {
                ++$fetches;

                return "User-agent: *\nDisallow: /private\n";
            },
        );

        $guard->assertAllowed('https://example.test/public', 'CrawlerX/1.7');
        $guard->assertAllowed('https://example.test/other', 'CrawlerX/1.7');

        self::assertSame(1, $fetches);
    }

    public function test_each_host_fetches_its_own_robots_txt(): void
    {
        $fetches = [];
        $guard = $this->guard(
            static function (string $url) use (&$fetches): ?string {
                $fetches[] = $url;

                return "User-agent: *\nDisallow: /private\n";
            },
        );

        $guard->assertAllowed('https://one.test/a', 'ua');
        $guard->assertAllowed('https://two.test/b', 'ua');

        self::assertSame(
            ['https://one.test/robots.txt', 'https://two.test/robots.txt'],
            $fetches,
        );
    }

    public function test_shared_cache_avoids_refetching_across_instances(): void
    {
        $fetches = 0;
        $cache = new ArrayCache();
        $fetcher = static function (string $url) use (&$fetches): ?string {
            ++$fetches;

            return "User-agent: *\nDisallow: /private\n";
        };

        $this->guard($fetcher, $cache)->assertAllowed('https://example.test/a', 'ua');
        $this->guard($fetcher, $cache)->assertAllowed('https://example.test/b', 'ua');

        self::assertSame(1, $fetches);
    }

    public function test_fails_open_and_logs_when_robots_txt_is_unreachable(): void
    {
        $logs = [];
        $guard = $this->guard(
            static function (string $url): ?string {
                return null;
            },
            logger: static function (string $message) use (&$logs): void {
                $logs[] = $message;
            },
        );

        $guard->assertAllowed('https://example.test/anything', 'ua');

        self::assertCount(1, $logs);
        self::assertStringContainsString('example.test', $logs[0]);
    }

    public function test_an_unreachable_robots_txt_is_not_cached_across_instances(): void
    {
        $fetches = 0;
        $cache = new ArrayCache();
        $fetcher = static function (string $url) use (&$fetches): ?string {
            ++$fetches;

            return null;
        };

        $this->guard($fetcher, $cache)->assertAllowed('https://example.test/a', 'ua');
        $this->guard($fetcher, $cache)->assertAllowed('https://example.test/b', 'ua');

        self::assertSame(2, $fetches);
    }

    public function test_fails_closed_with_a_typed_exception_on_disallow(): void
    {
        $guard = $this->guard(
            static fn(string $url): ?string => "User-agent: *\nDisallow: /blocked\n",
        );

        try {
            $guard->assertAllowed('https://example.test/blocked', 'ua');
            self::fail('A RobotsDisallowedException was expected.');
        } catch (RobotsDisallowedException $exception) {
            self::assertStringContainsString('https://example.test/blocked', $exception->getMessage());
            self::assertStringContainsString('/blocked', $exception->getMessage());
        }
    }

    public function test_an_empty_robots_txt_allows_everything(): void
    {
        $guard = $this->guard(static fn(string $url): ?string => '');

        $guard->assertAllowed('https://example.test/anything', 'ua');

        self::assertNull($guard->crawlDelaySeconds('https://example.test/anything', 'ua'));
    }

    public function test_a_url_without_a_host_skips_the_check(): void
    {
        $fetches = 0;
        $guard = $this->guard(
            static function (string $url) use (&$fetches): ?string {
                ++$fetches;

                return "User-agent: *\nDisallow: /\n";
            },
        );

        $guard->assertAllowed('/relative/path', 'ua');

        self::assertSame(0, $fetches);
    }

    public function test_crawl_delay_seconds_is_exposed(): void
    {
        $guard = $this->guard(
            static fn(string $url): ?string => "User-agent: *\nCrawl-delay: 4\n",
        );

        self::assertSame(4.0, $guard->crawlDelaySeconds('https://example.test/a', 'ua'));
    }

    public function test_cache_read_and_write_failures_fail_open(): void
    {
        $cache = new class implements \Psr\SimpleCache\CacheInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new \RuntimeException('down');
            }

            public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
            {
                throw new \RuntimeException('down');
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $fetches = 0;
        $guard = $this->guard(
            static function (string $url) use (&$fetches): ?string {
                ++$fetches;

                return null;
            },
            $cache,
        );

        $guard->assertAllowed('https://example.test/a', 'ua');
        $guard->assertAllowed('https://example.test/a', 'ua');

        self::assertSame(1, $fetches);
    }

    /**
     * @param  \Closure(string): ?string  $fetcher
     * @param  \Closure(string): void|null  $logger
     */
    private function guard(\Closure $fetcher, ?\Psr\SimpleCache\CacheInterface $cache = null, ?\Closure $logger = null): RobotsGuard
    {
        return new RobotsGuard($fetcher, $cache, 3600, $logger);
    }
}
