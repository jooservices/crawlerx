<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Request;

use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Request\SessionProfile;
use PHPUnit\Framework\TestCase;

final class SessionProfileTest extends TestCase
{
    public function test_cookies_merge_with_login_cookies_winning(): void
    {
        $profile = $this->profile(['Cookie' => 'a=1; b=2; broken; empty=']);
        $options = new CrawlOptionsDto(http: new HttpOptionsDto(headers: ['X-Extra' => 'y']));
        $session = $this->session(['b' => 'session', 'c' => '3']);

        $applied = SessionProfile::apply($profile, $options, $session, ['c' => 'login'], null);

        self::assertSame('a=1; b=session; c=login', $applied->http->headers['Cookie']);
        self::assertSame('y', $applied->http->headers['X-Extra']);
    }

    public function test_no_cookies_leaves_the_cookie_header_unset(): void
    {
        $applied = SessionProfile::apply($this->profile([]), null, null, [], null);

        self::assertArrayNotHasKey('Cookie', $applied->http->headers);
        self::assertArrayNotHasKey('User-Agent', $applied->http->headers);
    }

    public function test_session_user_agent_beats_request_user_agent_and_reaches_playwright(): void
    {
        $profile = $this->profile([], new PlaywrightProfileDto());

        $fromSession = SessionProfile::apply($profile, null, $this->session([], 'SessionUA'), [], 'RequestUA');
        $fromRequest = SessionProfile::apply($profile, null, null, [], 'RequestUA');

        self::assertSame('SessionUA', $fromSession->http->headers['User-Agent']);
        self::assertSame('SessionUA', $fromSession->playwright?->userAgent);
        self::assertSame('RequestUA', $fromRequest->http->headers['User-Agent']);
        self::assertSame('RequestUA', $fromRequest->playwright?->userAgent);
    }

    /**
     * @param array<string, string> $headers
     */
    private function profile(array $headers, ?PlaywrightProfileDto $playwright = null): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'sample',
            displayName: 'Sample',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [],
            http: new HttpProfileDto(headers: $headers),
            playwright: $playwright,
        );
    }

    /**
     * @param array<string, string> $cookies
     * @return array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}
     */
    private function session(array $cookies, ?string $userAgent = null): array
    {
        return ['cookies' => $cookies, 'userAgent' => $userAgent, 'source' => 'test', 'expiresAt' => 0, 'storageState' => null, 'challengeCount' => 0];
    }
}
