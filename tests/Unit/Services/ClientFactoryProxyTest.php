<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Services;

use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\Request\SessionProfile;
use JOOservices\CrawlerX\Services\ClientFactory;
use PHPUnit\Framework\TestCase;

final class ClientFactoryProxyTest extends TestCase
{
    private string|false $previous;

    protected function setUp(): void
    {
        $this->previous = getenv('CRAWLERX_HTTP_PROXY');
        putenv('CRAWLERX_HTTP_PROXY');
    }

    protected function tearDown(): void
    {
        putenv($this->previous === false ? 'CRAWLERX_HTTP_PROXY' : 'CRAWLERX_HTTP_PROXY=' . $this->previous);
    }

    public function test_no_proxy_by_default(): void
    {
        self::assertNull(ClientFactory::resolveProxy([]));
        self::assertNull(ClientFactory::resolveProxy(['proxy' => '  ']));
    }

    public function test_environment_is_the_process_default(): void
    {
        putenv('CRAWLERX_HTTP_PROXY=http://127.0.0.1:8118');

        self::assertSame('http://127.0.0.1:8118', ClientFactory::resolveProxy([]));
    }

    public function test_explicit_option_wins_over_the_environment(): void
    {
        putenv('CRAWLERX_HTTP_PROXY=http://127.0.0.1:8118');

        self::assertSame('http://proxy.test:3128', ClientFactory::resolveProxy(['proxy' => ' http://proxy.test:3128 ']));
    }

    public function test_dtos_carry_the_proxy(): void
    {
        self::assertSame('http://p:1', (new HttpOptionsDto(proxy: 'http://p:1'))->proxy);
        self::assertSame('http://p:1', (new HttpProfileDto(proxy: 'http://p:1'))->proxy);
        self::assertNull((new HttpProfileDto())->proxy);
    }

    public function test_session_profile_keeps_the_profile_proxy(): void
    {
        $profile = new SiteProfileDto(
            slug: 'proxy-test',
            displayName: 'Proxy test',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::HttpOnly,
            fetchChain: [FetchMethod::Http],
            http: new HttpProfileDto(proxy: 'http://p:1'),
        );

        $applied = SessionProfile::apply($profile, null, ['cookie' => 'a=b', 'userAgent' => 'ua'], [], 'ua');

        self::assertSame('http://p:1', $applied->http->proxy);
    }
}
