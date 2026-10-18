<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FetchFallbackChainBehaviourTest extends TestCase
{
    private const URL = 'https://example.test/movie/1';

    public function test_a_method_without_a_registered_handler_is_recorded_and_skipped(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, 200, '<html>fine</html>'),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Playwright, FetchMethod::Http]);

        self::assertSame(FetchMethod::Http, $result->methodUsed);
        self::assertSame('handler not registered', $result->attempts[0]['error']);
        self::assertFalse($result->attempts[0]['ok']);
    }

    public function test_when_nothing_can_be_fetched_the_error_is_a_retryable_network_failure(): void
    {
        $chain = new FetchFallbackChain([]);

        try {
            $chain->fetch(self::URL, $this->profile(), [FetchMethod::Playwright]);
            self::fail('A CrawlFetchException was expected.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
            self::assertTrue($exception->retryable);
            self::assertStringContainsString('All fetch methods exhausted', $exception->getMessage());
            self::assertSame('playwright', $exception->fetch?->methodUsed);
        }
    }

    public function test_flaresolverr_runs_when_it_is_the_only_requested_method(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Flaresolverr->value => $this->handler(FetchMethod::Flaresolverr, 200, '<html>solved</html>'),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Flaresolverr]);

        self::assertSame(FetchMethod::Flaresolverr, $result->methodUsed);
        self::assertTrue($result->ok);
    }

    public function test_flaresolverr_runs_when_no_earlier_method_could_execute(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Flaresolverr->value => $this->handler(FetchMethod::Flaresolverr, 200, '<html>solved</html>'),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Playwright, FetchMethod::Flaresolverr]);

        self::assertSame(FetchMethod::Flaresolverr, $result->methodUsed);
        self::assertSame('handler not registered', $result->attempts[0]['error']);
    }

    public function test_flaresolverr_runs_after_a_browser_that_never_got_an_answer(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Playwright->value => $this->handler(FetchMethod::Playwright, 0, ''),
            FetchMethod::Flaresolverr->value => $this->handler(FetchMethod::Flaresolverr, 200, '<html>solved</html>'),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Playwright, FetchMethod::Flaresolverr]);

        self::assertSame(FetchMethod::Flaresolverr, $result->methodUsed);
        self::assertSame(FetchMethod::Playwright->value, $result->attempts[0]['method']);
        self::assertSame(0, $result->attempts[0]['status']);
    }

    public function test_a_silent_browser_and_a_failing_flaresolverr_still_end_as_a_network_failure(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Playwright->value => $this->handler(FetchMethod::Playwright, 0, ''),
            FetchMethod::Flaresolverr->value => $this->handler(FetchMethod::Flaresolverr, 0, ''),
        ]);

        try {
            $chain->fetch(self::URL, $this->profile(), [FetchMethod::Playwright, FetchMethod::Flaresolverr]);
            self::fail('A CrawlFetchException was expected.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
            self::assertCount(2, $exception->fetch?->attempts ?? []);
        }
    }

    public function test_flaresolverr_is_still_skipped_after_a_real_answer_without_a_challenge(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, 200, '<html>fine</html>'),
            FetchMethod::Flaresolverr->value => $this->handler(FetchMethod::Flaresolverr, 200, '<html>solved</html>'),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);

        self::assertSame(FetchMethod::Http, $result->methodUsed);
    }

    /**
     * @return iterable<string, array{int, string, ?string}>
     */
    public static function authRequiredPages(): iterable
    {
        yield 'unauthorized status' => [401, '<html>x</html>', null];
        yield 'login required text' => [200, '<html><body><p>Login Required to view</p></body></html>', null];
        yield 'please log in text with entities' => [200, '<body>Please&nbsp;log   in</body>', null];
        yield 'redirected to a login path' => [200, '<html><body>welcome</body></html>', 'https://example.test/login?next=/movie/1'];
        yield 'redirected to a sign-in path' => [200, '<html>ok</html>', 'https://example.test/sign-in/'];
    }

    #[DataProvider('authRequiredPages')]
    public function test_pages_that_demand_authentication_are_a_non_retryable_auth_error(int $status, string $body, ?string $finalUrl): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, $status, $body, $finalUrl),
        ]);

        try {
            $chain->fetch(self::URL, $this->profile(), [FetchMethod::Http]);
            self::fail('A CrawlFetchException was expected.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::AuthRequired, $exception->errorCode);
            self::assertFalse($exception->retryable);
        }
    }

    public function test_login_words_inside_scripts_and_styles_do_not_count_as_a_login_wall(): void
    {
        $body = '<html><head><style>.x:after{content:"login required"}</style>'
            . '<script>var m = "please log in";</script></head><body>Real content</body></html>';
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, 200, $body),
        ]);

        $result = $chain->fetch(self::URL, $this->profile(), [FetchMethod::Http]);

        self::assertTrue($result->ok);
    }

    public function test_cookies_from_a_successful_browser_fetch_are_handed_off_for_later_http_fetches(): void
    {
        $store = new CookieHandoffStore();
        $chain = new FetchFallbackChain([
            FetchMethod::Playwright->value => $this->handler(
                FetchMethod::Playwright,
                200,
                '<html>ok</html>',
                'https://cdn.example.test/final',
                ['cf_clearance' => 'abc'],
            ),
        ], $store);

        $chain->fetch(self::URL, $this->profile(cookieHandoff: true), [FetchMethod::Playwright]);

        self::assertSame(['cf_clearance' => 'abc'], $store->get('cdn.example.test'));
        self::assertSame($store, $chain->cookies());
    }

    public function test_cookies_are_not_handed_off_when_the_profile_does_not_ask_for_it(): void
    {
        $store = new CookieHandoffStore();
        $chain = new FetchFallbackChain([
            FetchMethod::Playwright->value => $this->handler(FetchMethod::Playwright, 200, '<html>ok</html>', null, ['a' => 'b']),
        ], $store);

        $chain->fetch(self::URL, $this->profile(cookieHandoff: false), [FetchMethod::Playwright]);

        self::assertSame([], $store->get('example.test'));
    }

    /**
     * @param array<string, string> $cookies
     */
    private function handler(FetchMethod $supports, int $status, string $body, ?string $finalUrl = null, array $cookies = []): FetchMethodHandler
    {
        return new class ($supports, $status, $body, $finalUrl, $cookies) implements FetchMethodHandler {
            /**
             * @param array<string, string> $cookies
             */
            public function __construct(
                private FetchMethod $supports,
                private int $status,
                private string $body,
                private ?string $finalUrl,
                private array $cookies,
            ) {
            }

            public function supports(FetchMethod $method): bool
            {
                return $method === $this->supports;
            }

            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                return new FetchResultDto(
                    ok: $this->status >= 200 && $this->status < 300,
                    body: $this->body,
                    status: $this->status,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: false,
                    finalUrl: $this->finalUrl ?? $url,
                    cookies: $this->cookies,
                );
            }
        };
    }

    private function profile(bool $cookieHandoff = false): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Playwright],
            http: new HttpProfileDto(),
            cookieHandoffAfterBrowser: $cookieHandoff,
        );
    }
}
