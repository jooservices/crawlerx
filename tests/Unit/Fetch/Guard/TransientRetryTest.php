<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Guard;

use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransientRetryTest extends TestCase
{
    public function test_retries_only_502_503_and_504_without_retry_after(): void
    {
        $retry = new TransientRetry();

        foreach ([502, 503, 504] as $status) {
            self::assertTrue($retry->isEligible($this->createResult($status)));
        }

        self::assertFalse($retry->isEligible($this->createResult(502, headers: ['Retry-After' => ['1']])));
        self::assertFalse($retry->isEligible($this->createResult(503, headers: ['retry-after' => ['1']])));
        self::assertFalse($retry->isEligible($this->createResult(504, headers: ['Retry-After' => ['1']])));
        self::assertFalse($retry->isEligible($this->createResult(429)));
        self::assertFalse($retry->isEligible($this->createResult(404)));
        self::assertFalse($retry->isEligible($this->createResult(410)));
    }

    public function test_retries_explicit_network_errors_but_not_timeout_auth_or_ssrf(): void
    {
        $retry = new TransientRetry();

        self::assertSame(
            TransientRetry::NETWORK_ERROR,
            TransientRetry::errorCode(new NetworkConnectionException(new Request('GET', 'https://example.test'), 'synthetic diagnostic')),
        );
        self::assertSame(
            TransientRetry::NETWORK_TIMEOUT,
            TransientRetry::errorCode(new TimeoutException(new Request('GET', 'https://example.test'), 'synthetic diagnostic')),
        );
        self::assertSame(TransientRetry::FETCH_ERROR, TransientRetry::errorCode(new RuntimeException('private diagnostic')));
        self::assertTrue($retry->isEligible($this->createResult(0, error: TransientRetry::NETWORK_ERROR)));
        self::assertFalse($retry->isEligible($this->createResult(0, error: TransientRetry::NETWORK_TIMEOUT)));
        self::assertFalse($retry->isEligible($this->createResult(0, error: 'ssrf_blocked')));
        self::assertFalse($retry->isEligible($this->createResult(401, error: 'auth_required')));
        self::assertFalse($retry->isEligible($this->createResult(403, challenge: true)));
    }

    public function test_caps_internal_retries_at_two(): void
    {
        $retry = new TransientRetry();
        $result = $this->createResult(502);

        self::assertTrue($retry->canRetry($result, 0));
        self::assertTrue($retry->canRetry($result, 1));
        self::assertFalse($retry->canRetry($result, 2));
        self::assertFalse($retry->canRetry($this->createResult(404), 0));
    }

    public function test_requires_budget_for_delay_and_another_attempt(): void
    {
        $retry = new TransientRetry();

        self::assertTrue($retry->canAfford(1.5, 0.5));
        self::assertFalse($retry->canAfford(1.49, 0.5));
    }

    public function test_jittered_backoff_stays_between_half_and_two_seconds(): void
    {
        $retry = new TransientRetry();

        $firstDelay = $retry->delaySeconds(1);
        $secondDelay = $retry->delaySeconds(2);

        self::assertGreaterThanOrEqual(0.5, $firstDelay);
        self::assertLessThanOrEqual(1.0, $firstDelay);
        self::assertGreaterThanOrEqual(1.0, $secondDelay);
        self::assertLessThanOrEqual(2.0, $secondDelay);
    }

    /** @param array<string, list<string>> $headers */
    private function createResult(
        int $status,
        ?string $error = null,
        array $headers = [],
        bool $challenge = false,
    ): FetchResultDto {
        return new FetchResultDto(
            ok: false,
            body: '',
            status: $status,
            methodUsed: FetchMethod::Http,
            elapsedMs: 1,
            challengeDetected: $challenge,
            headers: $headers,
            error: $error,
        );
    }
}
