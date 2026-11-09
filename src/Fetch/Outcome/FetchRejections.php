<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Outcome;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\TerminalStatus;

/**
 * Builds the exceptions and fetch metadata the chain reports when a fetch does not succeed.
 */
final class FetchRejections
{
    public static function ssrfBlocked(FetchResultDto $result, FetchResultDto $last): CrawlFetchException
    {
        $detail = is_string($result->ssrfReason) && $result->ssrfReason !== '' ? ' reason=' . $result->ssrfReason : '';

        return new CrawlFetchException(
            message: 'Fetch blocked by SSRF policy.' . $detail,
            errorCode: CrawlErrorCode::SsrfBlocked,
            retryable: false,
            retryAfterSeconds: null,
            fetch: self::rejectedMeta($last, CrawlErrorCode::SsrfBlocked),
        );
    }

    public static function authRequired(string $url, FetchResultDto $last): CrawlFetchException
    {
        return new CrawlFetchException(
            message: 'Authentication is required for URL [' . $url . '].',
            errorCode: CrawlErrorCode::AuthRequired,
            retryable: false,
            retryAfterSeconds: null,
            fetch: self::rejectedMeta($last, CrawlErrorCode::AuthRequired),
        );
    }

    public static function terminal(string $url, TerminalStatus $terminal, FetchResultDto $last): CrawlFetchException
    {
        return new CrawlFetchException(
            message: 'Terminal fetch status for URL [' . $url . '].',
            errorCode: $terminal->code,
            retryable: $terminal->retryable,
            retryAfterSeconds: $terminal->retryAfterSeconds,
            fetch: self::rejectedMeta($last, $terminal->code),
        );
    }

    /**
     * @param list<array{started_at?: string, duration_ms?: int, http_status?: ?int, cf_mitigated?: ?string, outcome?: string, error_code?: ?string, method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}> $attempts
     * @param list<FetchMethod> $plan
     */
    public static function exhausted(string $url, ?FetchResultDto $last, array $attempts, array $plan, FetchBudget $budget, bool $sawChallenge): CrawlFetchException
    {
        $lastAttempt = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $budgetExhausted = is_array($lastAttempt) && ($lastAttempt['error'] ?? null) === 'fetch budget exhausted';
        $fetch = self::failureMeta($last, $attempts, $plan, $budget, $sawChallenge);

        if ($budget->remainingSeconds() <= 0.0 || $budgetExhausted) {
            return new CrawlFetchException(
                message: 'Fetch budget exhausted for URL [' . $url . '].',
                errorCode: CrawlErrorCode::Timeout,
                retryable: true,
                retryAfterSeconds: null,
                fetch: $fetch,
            );
        }

        $code = $sawChallenge ? CrawlErrorCode::Challenge : CrawlErrorCode::Network;

        return new CrawlFetchException(
            message: 'All fetch methods exhausted for URL [' . $url . '].',
            errorCode: $code,
            retryable: $code->defaultRetryable(),
            retryAfterSeconds: null,
            fetch: $fetch,
        );
    }

    /** The attempt log of a result whose last attempt is rewritten as a failure with the given code. */
    public static function rejectedMeta(FetchResultDto $result, CrawlErrorCode $code): FetchMetaDto
    {
        $attempts = $result->attempts;
        $key = array_key_last($attempts);
        if ($key !== null) {
            $attempts[$key]['ok'] = false;
            $attempts[$key]['outcome'] = 'error';
            $attempts[$key]['error_code'] = $code->value;
            $attempts[$key]['error'] = $code->value;
        }

        return $result->withAttempts($attempts)->toMeta();
    }

    /**
     * @param list<array{started_at?: string, duration_ms?: int, http_status?: ?int, cf_mitigated?: ?string, outcome?: string, error_code?: ?string, method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}> $attempts
     * @param list<FetchMethod> $plan
     */
    private static function failureMeta(?FetchResultDto $last, array $attempts, array $plan, FetchBudget $budget, bool $sawChallenge): FetchMetaDto
    {
        return new FetchMetaDto(
            methodUsed: $last?->methodUsed->value ?? ($plan[0] ?? FetchMethod::Http)->value,
            elapsedMs: (int) round($budget->elapsedSeconds() * 1000),
            challengeDetected: $sawChallenge,
            attempts: $attempts,
        );
    }
}
