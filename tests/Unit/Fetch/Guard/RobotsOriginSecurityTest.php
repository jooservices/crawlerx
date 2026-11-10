<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use PHPUnit\Framework\TestCase;

final class RobotsOriginSecurityTest extends TestCase
{
    public function test_robots_rules_normalize_case_and_trailing_dots_but_keep_the_port(): void
    {
        $requests = [];
        $guard = new RobotsGuard(static function (string $url) use (&$requests): ?string {
            $requests[] = $url;

            return "User-agent: *\nDisallow: /private\n";
        });

        try {
            $guard->assertAllowed('https://EXAMPLE.test.:8443/public', '*');
            $guard->assertAllowed('https://example.test:8443/private', '*');
            self::fail('A private URL must remain blocked for an equivalent host spelling.');
        } catch (RobotsDisallowedException) {
            self::assertSame(['https://example.test:8443/robots.txt'], $requests);
        }
    }

    public function test_robots_rules_are_not_shared_across_ports_or_schemes(): void
    {
        $requests = [];
        $guard = new RobotsGuard(static function (string $url) use (&$requests): ?string {
            $requests[] = $url;

            return '';
        });

        $guard->assertAllowed('https://example.test/path', '*');
        $guard->assertAllowed('https://example.test:8443/path', '*');
        $guard->assertAllowed('http://example.test/path', '*');

        self::assertSame([
            'https://example.test/robots.txt',
            'https://example.test:8443/robots.txt',
            'http://example.test/robots.txt',
        ], $requests);
    }
}
