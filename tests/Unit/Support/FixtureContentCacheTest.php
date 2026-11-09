<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use JOOservices\CrawlerX\Tests\Support\FixtureContentCache;
use PHPUnit\Framework\TestCase;

final class FixtureContentCacheTest extends TestCase
{
    public function test_fixture_contents_are_cached_for_the_process(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'crawlerx-fixture-');
        if ($path === false) {
            self::fail('Unable to create a temporary fixture.');
        }

        try {
            self::assertSame(5, file_put_contents($path, 'first'));
            self::assertSame('first', FixtureContentCache::read($path));
            self::assertSame(6, file_put_contents($path, 'second'));
            self::assertSame('first', FixtureContentCache::read($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
