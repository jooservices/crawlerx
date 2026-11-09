<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Outcome;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Attempt\AttemptRow;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Outcome\FetchRejections;
use PHPUnit\Framework\TestCase;

final class FetchRejectionsTest extends TestCase
{
    public function test_ssrf_block_is_not_retryable_and_carries_the_reason(): void
    {
        $result = new FetchResultDto(false, '', 0, FetchMethod::Playwright, 1, false, ssrfReason: 'private_address');

        $exception = FetchRejections::ssrfBlocked($result, $this->withAttempt($result));

        self::assertSame('Fetch blocked by SSRF policy. reason=private_address', $exception->getMessage());
        self::assertSame(CrawlErrorCode::SsrfBlocked, $exception->errorCode);
        self::assertFalse($exception->retryable);
        self::assertSame('ssrf_blocked', $exception->fetch?->attempts[0]['error_code']);
    }

    public function test_ssrf_block_without_reason_has_no_detail(): void
    {
        $result = new FetchResultDto(false, '', 0, FetchMethod::Playwright, 1, false);

        self::assertSame('Fetch blocked by SSRF policy.', FetchRejections::ssrfBlocked($result, $this->withAttempt($result))->getMessage());
    }

    public function test_auth_required_rewrites_the_last_attempt(): void
    {
        $result = new FetchResultDto(true, '', 200, FetchMethod::Http, 1, false);

        $exception = FetchRejections::authRequired('https://a.test/x', $this->withAttempt($result));

        self::assertSame('Authentication is required for URL [https://a.test/x].', $exception->getMessage());
        self::assertSame(CrawlErrorCode::AuthRequired, $exception->errorCode);
        self::assertFalse($exception->fetch?->attempts[0]['ok']);
        self::assertSame('error', $exception->fetch?->attempts[0]['outcome']);
    }

    public function test_exhausted_is_a_network_error_or_a_challenge(): void
    {
        $budget = new FetchBudget(100);

        $network = FetchRejections::exhausted('https://a.test', null, [], [], $budget, false);
        $challenge = FetchRejections::exhausted('https://a.test', null, [], [FetchMethod::Playwright], $budget, true);

        self::assertSame(CrawlErrorCode::Network, $network->errorCode);
        self::assertSame('http', $network->fetch?->methodUsed);
        self::assertSame(CrawlErrorCode::Challenge, $challenge->errorCode);
        self::assertSame('playwright', $challenge->fetch?->methodUsed);
        self::assertTrue($challenge->fetch?->challengeDetected);
    }

    public function test_exhausted_is_a_retryable_timeout_when_the_budget_ran_out(): void
    {
        $skipped = AttemptRow::build(FetchMethod::Http, 0, 0, false, false, 'fetch budget exhausted', skipped: true);

        $exception = FetchRejections::exhausted('https://a.test', null, [$skipped], [FetchMethod::Http], new FetchBudget(100), false);

        self::assertSame(CrawlErrorCode::Timeout, $exception->errorCode);
        self::assertTrue($exception->retryable);
        self::assertSame('Fetch budget exhausted for URL [https://a.test].', $exception->getMessage());
    }

    private function withAttempt(FetchResultDto $result): FetchResultDto
    {
        return $result->withAttempts([AttemptRow::build($result->methodUsed, 1, $result->status, false, $result->ok, $result->error)]);
    }
}
