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
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use PHPUnit\Framework\TestCase;

final class RobotsRedirectSecurityTest extends TestCase
{
    public function test_a_redirected_destination_is_checked_against_its_robots_rules(): void
    {
        $robotsRequests = [];
        $guard = new RobotsGuard(static function (string $url) use (&$robotsRequests): ?string {
            $robotsRequests[] = $url;

            return str_contains($url, 'redirect.test')
                ? "User-agent: *\nDisallow: /private\n"
                : '';
        });
        $handler = new class implements FetchMethodHandler {
            public function supports(FetchMethod $method): bool
            {
                return $method === FetchMethod::Http;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                return new FetchResultDto(
                    ok: true,
                    body: '<html><body>redirected page response body</body></html>',
                    status: 200,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: false,
                    finalUrl: 'https://redirect.test/private',
                );
            }
        };
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $handler], robotsGuard: $guard);

        try {
            $chain->fetch('https://source.test/start', $this->profile(), [FetchMethod::Http]);
            self::fail('A final URL disallowed by its robots policy must be rejected.');
        } catch (RobotsDisallowedException) {
            self::assertSame([
                'https://source.test/robots.txt',
                'https://redirect.test/robots.txt',
            ], $robotsRequests);
        }
    }

    public function test_browser_and_flaresolverr_methods_fail_closed_when_robots_are_enabled(): void
    {
        foreach ([FetchMethod::Playwright, FetchMethod::PuppeteerStealth, FetchMethod::Flaresolverr] as $method) {
            $counter = (object) ['calls' => 0];
            $handler = new class ($method, $counter) implements FetchMethodHandler {
                public function __construct(private readonly FetchMethod $supported, private readonly object $counter)
                {
                }

                public function supports(FetchMethod $method): bool
                {
                    return $method === $this->supported;
                }

                public function fetch(
                    string $url,
                    SiteProfileDto $profile,
                    FetchMethod $method,
                    ?CrawlOptionsDto $options = null,
                ): FetchResultDto {
                    ++$this->counter->calls;

                    return new FetchResultDto(true, '<html>unexpected</html>', 200, $method, 1, false, $url);
                }
            };
            $chain = new FetchFallbackChain(
                [$method->value => $handler],
                robotsGuard: new RobotsGuard(static fn(string $url): ?string => ''),
            );

            try {
                $chain->fetch('https://source.test/start', $this->profile(), [$method]);
                self::fail("{$method->value} must be skipped when its redirects cannot be checked.");
            } catch (CrawlFetchException $exception) {
                self::assertSame(0, $counter->calls);
                self::assertSame('redirect guard unavailable', $exception->fetch?->attempts[0]['error']);
            }
        }
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'test',
            displayName: 'Test',
            baseUrl: 'https://source.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(),
        );
    }
}
