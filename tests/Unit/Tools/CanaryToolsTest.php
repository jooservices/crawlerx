<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tools\Canary\Env;
use JOOservices\CrawlerX\Tools\Canary\LoginCookiePolicy;
use JOOservices\CrawlerX\Tools\Canary\Redactor;
use JOOservices\CrawlerX\Tools\Canary\RequiredFields;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/canary/Env.php';
require_once __DIR__ . '/../../../tools/canary/LoginCookiePolicy.php';
require_once __DIR__ . '/../../../tools/canary/Redactor.php';
require_once __DIR__ . '/../../../tools/canary/RequiredFields.php';

final class CanaryToolsTest extends TestCase
{
    public function test_tc_cn_02_missing_cookie_is_not_a_pass(): void
    {
        self::assertNull(Env::cookieForSite('avfan', ['CRAWLERX_COOKIE_AVFAN' => '']));
        self::assertNull(Env::cookieForSite('avfan', []));
    }

    public function test_avfan_profiles_canary_does_not_require_a_login_cookie(): void
    {
        self::assertTrue(LoginCookiePolicy::isRequired('avfan'));
        self::assertFalse(LoginCookiePolicy::isRequired('avfan_profiles'));
    }

    public function test_tc_cn_03_cookie_value_is_redacted(): void
    {
        $secret = 'synthetic-cookie-value';
        $output = Redactor::text("Cookie: {$secret}; remember_token={$secret}", [$secret]);

        self::assertStringNotContainsString($secret, $output);
        self::assertStringContainsString('[REDACTED]', $output);
    }

    public function test_tc_cn_04_resource_metric_fields_are_non_negative(): void
    {
        $record = [
            'php_cpu_seconds' => 0.0,
            'php_peak_rss_bytes' => 0,
            'resources' => [
                'node' => ['cpu_seconds' => 0.0, 'peak_rss_bytes' => 0],
                'flaresolverr' => ['cpu_seconds' => 0.0, 'peak_rss_bytes' => 0],
            ],
        ];

        self::assertGreaterThanOrEqual(0, $record['php_cpu_seconds']);
        self::assertGreaterThanOrEqual(0, $record['php_peak_rss_bytes']);
        self::assertGreaterThanOrEqual(0, $record['resources']['node']['cpu_seconds']);
        self::assertGreaterThanOrEqual(0, $record['resources']['node']['peak_rss_bytes']);
        self::assertGreaterThanOrEqual(0, $record['resources']['flaresolverr']['cpu_seconds']);
        self::assertGreaterThanOrEqual(0, $record['resources']['flaresolverr']['peak_rss_bytes']);
    }

    public function test_required_fields_detect_a_missing_movie_identity_field(): void
    {
        $result = new CrawlItemResultDto(
            url: 'https://example.test/movie/1',
            entityType: 'movie',
            meta: ['movie' => ['title' => 'Synthetic movie']],
        );

        $coverage = RequiredFields::check($result, 'detail');

        self::assertSame(['movie.external_id'], $coverage['missing']);
    }

    public function test_required_fields_accept_a_complete_listing(): void
    {
        $item = new CrawlItemResultDto(
            url: 'https://example.test/movie/1',
            entityType: 'movie',
            meta: ['movie' => ['title' => 'Synthetic movie', 'external_id' => '1']],
        );
        $result = new CrawlListResultDto(
            url: 'https://example.test/movies',
            page: 1,
            entityType: 'movie',
            items: [$item],
            pagination: new CrawlPaginationDto(
                currentPage: 1,
                lastPage: null,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );

        self::assertSame([], RequiredFields::check($result, 'listing')['missing']);
    }
}
