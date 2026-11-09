<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Attempt;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Attempt\AttemptRow;
use PHPUnit\Framework\TestCase;

final class AttemptRowTest extends TestCase
{
    public function test_successful_row(): void
    {
        $row = AttemptRow::build(FetchMethod::Http, 12, 200, false, true, null, started: 1_700_000_000_123);

        self::assertSame('ok', $row['outcome']);
        self::assertNull($row['error_code']);
        self::assertNull($row['error']);
        self::assertSame(200, $row['http_status']);
        self::assertSame('2023-11-14T22:13:20.123Z', $row['started_at']);
        self::assertSame('http', $row['method']);
    }

    public function test_failure_codes_follow_challenge_status_and_default(): void
    {
        $challenge = AttemptRow::build(FetchMethod::Http, 0, 403, true, false, null);
        $http = AttemptRow::build(FetchMethod::Http, 0, 503, false, false, null);
        $network = AttemptRow::build(FetchMethod::Http, 0, 0, false, false, null);

        self::assertSame('challenge', $challenge['error_code']);
        self::assertSame('challenge detected', $challenge['error']);
        self::assertSame('http_503', $http['error_code']);
        self::assertSame('HTTP 503', $http['error']);
        self::assertSame('fetch_error', $network['error_code']);
        self::assertNull($network['http_status']);
    }

    public function test_skipped_row(): void
    {
        $row = AttemptRow::build(FetchMethod::Flaresolverr, 0, 0, false, false, 'handler not registered', skipped: true);

        self::assertSame('skipped', $row['outcome']);
        self::assertSame('skipped', $row['error_code']);
    }

    public function test_error_is_redacted_and_html_is_omitted(): void
    {
        $url = AttemptRow::build(FetchMethod::Http, 0, 0, false, false, 'failed https://secret.test/a?b=1 now');
        $html = AttemptRow::build(FetchMethod::Http, 0, 0, false, false, '<html>boom</html>');

        self::assertSame('failed [url] now', $url['error']);
        self::assertSame('HTML error omitted', $html['error']);
    }

    public function test_cf_mitigated_header_is_captured_case_insensitively(): void
    {
        $row = AttemptRow::build(FetchMethod::Http, 0, 403, true, false, null, headers: ['CF-Mitigated' => ['challenge']]);

        self::assertSame('challenge', $row['cf_mitigated']);
    }
}
