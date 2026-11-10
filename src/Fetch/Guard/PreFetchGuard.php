<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;

/**
 * Applies the host throttle and circuit breaker before a handler runs.
 * One instance serves one fetch: the circuit is consulted once, the throttle before every attempt.
 */
final class PreFetchGuard
{
    private bool $circuitChecked = false;

    public function __construct(
        private readonly ?HostThrottle $hostThrottle = null,
        private readonly ?HostCircuit $hostCircuit = null,
        private readonly ?float $robotsDelaySeconds = null,
    ) {
    }

    /** A synthetic 429 result when the host must not be hit now, null to go ahead. */
    public function check(string $url, SiteProfileDto $profile, FetchMethod $method, FetchBudget $budget): ?FetchResultDto
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $this->circuitChecked = true;

            return null;
        }

        if ($this->hostThrottle !== null) {
            $retryAfter = $this->hostThrottle->waitBeforeFetch($host, $this->throttle($profile->defaultThrottle), $budget, $method);
            if ($retryAfter !== null) {
                return self::rateLimited($method, $url, $retryAfter, 'throttle');
            }
        }

        if (! $this->circuitChecked) {
            $this->circuitChecked = true;
            $retryAfter = $this->hostCircuit?->beforeFetch($host);
            if ($retryAfter !== null) {
                return self::rateLimited($method, $url, $retryAfter, 'circuit');
            }
        }

        return null;
    }

    /** @param array<string, float>|null $settings
     *  @return array<string, float>|null
     */
    private function throttle(?array $settings): ?array
    {
        if ($this->robotsDelaySeconds === null || $this->robotsDelaySeconds <= 0.0) {
            return $settings;
        }

        $settings ??= [];
        $default = $settings['default_gap_seconds'] ?? 0.0;
        $settings['default_gap_seconds'] = max($default, $this->robotsDelaySeconds);
        $settings['max_gap_seconds'] = max($settings['max_gap_seconds'] ?? $settings['default_gap_seconds'], $this->robotsDelaySeconds);

        return $settings;
    }

    private static function rateLimited(FetchMethod $method, string $url, int $retryAfter, string $guard): FetchResultDto
    {
        return new FetchResultDto(
            ok: false,
            body: '',
            status: 429,
            methodUsed: $method,
            elapsedMs: 0,
            challengeDetected: false,
            finalUrl: $url,
            headers: [
                'Retry-After' => [(string) $retryAfter],
                'X-CrawlerX-Guard' => [$guard],
            ],
            error: CrawlErrorCode::RateLimited->value,
        );
    }
}
