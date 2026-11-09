<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use PHPUnit\Framework\TestCase;

final class RobotsGuardChainTest extends TestCase
{
    public function test_a_disallowed_url_throws_a_robots_exception_before_fetching(): void
    {
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $this->handler()],
            robotsGuard: $this->guard("User-agent: *\nDisallow: /blocked\n"),
        );

        $this->expectException(RobotsDisallowedException::class);

        $chain->fetch('https://example.test/blocked', $this->profile(), [FetchMethod::Http]);
    }

    public function test_an_allowed_url_fetches_normally(): void
    {
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $this->handler()],
            robotsGuard: $this->guard("User-agent: *\nDisallow: /blocked\n"),
        );

        $result = $chain->fetch('https://example.test/public', $this->profile(), [FetchMethod::Http]);

        self::assertTrue($result->ok);
    }

    public function test_a_site_can_disable_the_guard(): void
    {
        $fetches = 0;
        $guard = new RobotsGuard(
            static function (string $url) use (&$fetches): ?string {
                ++$fetches;

                return "User-agent: *\nDisallow: /blocked\n";
            },
        );
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $this->handler()],
            robotsGuard: $guard,
        );

        $result = $chain->fetch(
            'https://example.test/blocked',
            $this->profile(respectRobots: false),
            [FetchMethod::Http],
        );

        self::assertTrue($result->ok);
        self::assertSame(0, $fetches);
    }

    public function test_the_global_default_can_be_disabled_through_the_runtime_config(): void
    {
        $fetches = 0;
        $guard = new RobotsGuard(
            static function (string $url) use (&$fetches): ?string {
                ++$fetches;

                return "User-agent: *\nDisallow: /blocked\n";
            },
        );
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $this->handler()],
            runtime: new FetchRuntimeConfig(respectRobots: false),
            robotsGuard: $guard,
        );

        $result = $chain->fetch('https://example.test/blocked', $this->profile(), [FetchMethod::Http]);

        self::assertTrue($result->ok);
        self::assertSame(0, $fetches);
    }

    private function guard(string $robots): RobotsGuard
    {
        return new RobotsGuard(static fn(string $url): ?string => $robots);
    }

    private function handler(): FetchMethodHandler
    {
        return new class implements FetchMethodHandler {
            public function supports(FetchMethod $method): bool
            {
                return $method === FetchMethod::Http;
            }

            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                return new FetchResultDto(
                    ok: true,
                    body: '<html>ok</html>',
                    status: 200,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: false,
                    finalUrl: $url,
                );
            }
        };
    }

    private function profile(bool $respectRobots = true): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(),
            respectRobots: $respectRobots ? null : false,
        );
    }
}
