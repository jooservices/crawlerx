<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Session;

use Faker\Factory;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use PHPUnit\Framework\TestCase;

final class FetchFallbackSessionTest extends TestCase
{
    public function test_tc_se_01_flare_session_is_replayed_by_five_http_requests(): void
    {
        $http = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            $cookie = $profile->http->headers['Cookie'] ?? '';
            $userAgent = $profile->http->headers['User-Agent'] ?? '';
            $ok = str_contains($cookie, 'cf_clearance=flare-token') && $userAgent === 'Flare-UA';

            return $this->makeResult($method, $url, $ok, $ok ? '<html><body>authenticated fixture response</body></html>' : '<title>Just a moment...</title>');
        });
        $flareCalls = 0;
        $flare = new RecordingHandler(FetchMethod::Flaresolverr, function (SiteProfileDto $profile, FetchMethod $method, string $url) use (&$flareCalls): FetchResultDto {
            ++$flareCalls;

            return new FetchResultDto(
                ok: true,
                body: '<html><body>flare solved fixture response</body></html>',
                status: 200,
                methodUsed: $method,
                elapsedMs: 1,
                challengeDetected: false,
                finalUrl: $url,
                cookies: ['cf_clearance' => 'flare-token'],
                userAgent: 'Flare-UA',
            );
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $http, FetchMethod::Flaresolverr->value => $flare],
            sessions: new SessionStore(node: 'node-a'),
            runtime: new FetchRuntimeConfig(userAgent: 'Base-UA', userAgentPool: ['Base-UA']),
        );

        $first = $chain->fetch('https://fixture.test/cf-bound/1', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);
        self::assertSame(FetchMethod::Flaresolverr, $first->methodUsed);
        for ($index = 0; $index < 5; ++$index) {
            self::assertTrue($chain->fetch('https://fixture.test/cf-bound/' . ($index + 2), $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        }

        self::assertSame(1, $flareCalls);
        self::assertCount(6, $http->seen);
        self::assertSame('Flare-UA', $http->seen[1]['userAgent']);
    }

    public function test_tc_se_02_wrong_replayed_ua_forgets_the_session_and_resolves_again(): void
    {
        $store = new SessionStore(node: 'node-a');
        $store->put('fixture', ['cf_clearance' => 'old-token'], 'Old-UA', 'flaresolverr');
        $http = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            $valid = ($profile->http->headers['Cookie'] ?? '') === 'cf_clearance=new-token'
                && ($profile->http->headers['User-Agent'] ?? '') === 'New-UA';

            return $this->makeResult($method, $url, $valid, '<title>Just a moment...</title>');
        });
        $flare = new RecordingHandler(FetchMethod::Flaresolverr, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return new FetchResultDto(
                ok: true,
                body: '<html><body>flare solved fixture response</body></html>',
                status: 200,
                methodUsed: $method,
                elapsedMs: 1,
                challengeDetected: false,
                finalUrl: $url,
                cookies: ['cf_clearance' => 'new-token'],
                userAgent: 'New-UA',
            );
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $http, FetchMethod::Flaresolverr->value => $flare],
            sessions: $store,
            runtime: new FetchRuntimeConfig(userAgent: 'New-UA', userAgentPool: ['New-UA']),
        );

        self::assertTrue($chain->fetch('https://fixture.test/cf-bound/1', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr])->ok);
        self::assertSame('new-token', $store->get('fixture')['cookies']['cf_clearance']);
        self::assertSame('New-UA', $store->userAgent('fixture'));
        self::assertSame('cf_clearance=old-token', $http->seen[0]['cookies']);
    }

    public function test_tc_se_06_login_cookie_provider_authenticates_a_request(): void
    {
        $faker = Factory::create();
        $secret = $faker->sha256();
        $provider = new class ($secret) implements LoginCookieProvider {
            public function __construct(private readonly string $secret)
            {
            }

            public function cookiesFor(string $site): array
            {
                return ['remember_token' => $this->secret];
            }
        };
        $handler = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url) use ($secret): FetchResultDto {
            $ok = ($profile->http->headers['Cookie'] ?? '') === 'remember_token=' . $secret;

            return $this->makeResult($method, $url, $ok, $ok ? '<html><body>authenticated fixture response</body></html>' : '<html><body>login required</body></html>', $ok ? 200 : 401);
        });
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $handler],
            logins: $provider,
        );

        self::assertTrue($chain->fetch('https://fixture.test/login-wall/1', $this->profile(), [FetchMethod::Http])->ok);
    }

    public function test_tc_se_07_login_wall_without_cookie_is_auth_required_and_not_retryable(): void
    {
        $handler = new RecordingHandler(FetchMethod::Http, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return $this->makeResult($method, $url, false, '<html><body>login required</body></html>', 401);
        });
        $chain = new FetchFallbackChain([FetchMethod::Http->value => $handler]);

        try {
            $chain->fetch('https://fixture.test/login-wall/1', $this->profile(), [FetchMethod::Http]);
            self::fail('Expected auth_required.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::AuthRequired, $exception->errorCode);
            self::assertFalse($exception->retryable);
        }
    }

    public function test_storage_state_is_passed_to_the_browser_step(): void
    {
        $store = new SessionStore(node: 'node-a');
        $store->put('fixture', [], 'Fixture-UA', 'playwright', null, [
            'cookies' => [['name' => 'session', 'value' => 'value']],
            'origins' => [],
        ]);
        $handler = new RecordingHandler(FetchMethod::Playwright, function (SiteProfileDto $profile, FetchMethod $method, string $url): FetchResultDto {
            return new FetchResultDto(true, '<html><body>browser response body</body></html>', 200, $method, 1, false, $url);
        });
        $handler->inspectOptions = true;
        $chain = new FetchFallbackChain([FetchMethod::Playwright->value => $handler], sessions: $store);

        self::assertTrue($chain->fetch('https://fixture.test/page', $this->profile(), [FetchMethod::Playwright])->ok);
        self::assertSame('value', $handler->options[0]->storageState['cookies'][0]['value']);
        self::assertSame('Fixture-UA', $handler->seen[0]['userAgent']);
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'fixture',
            displayName: 'Fixture',
            baseUrl: 'https://fixture.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Flaresolverr],
            http: new HttpProfileDto(),
            playwright: new PlaywrightProfileDto(),
        );
    }

    private function makeResult(FetchMethod $method, string $url, bool $ok, string $body, int $status = 403): FetchResultDto
    {
        return new FetchResultDto($ok, $body, $ok ? 200 : $status, $method, 1, ! $ok, $url, error: $ok ? null : 'challenge');
    }
}

final class RecordingHandler implements FetchMethodHandler
{
    /** @var list<array{cookies: string, userAgent: string}> */
    public array $seen = [];

    /** @var list<CrawlOptionsDto> */
    public array $options = [];

    public bool $inspectOptions = false;

    public function __construct(
        private readonly FetchMethod $method,
        private readonly \Closure $callback,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $method === $this->method;
    }

    public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
    {
        $this->seen[] = [
            'cookies' => $profile->http->headers['Cookie'] ?? '',
            'userAgent' => $profile->http->headers['User-Agent'] ?? '',
        ];
        if ($this->inspectOptions && $options !== null) {
            $this->options[] = $options;
        }

        return ($this->callback)($profile, $method, $url, $options);
    }
}
