<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use Throwable;

final class FetchFallbackChain
{
    /**
     * @param  array<string, FetchMethodHandler>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
    ) {
    }

    /**
     * @param  list<FetchMethod>  $plan
     */
    public function fetch(
        string $url,
        SiteProfileDto $profile,
        array $plan,
        ?CrawlOptionsDto $options = null,
        ?CrawlType $type = null,
    ): FetchResultDto {
        $attempts = [];
        $last = null;
        $sawChallenge = false;
        $budget = FetchBudget::start($options?->fetch);
        $readyMarkers = $profile->readyMarkersFor($type);

        foreach ($plan as $method) {
            if ($method === FetchMethod::Flaresolverr && ! $sawChallenge) {
                continue;
            }

            if (! $budget->canStart($method)) {
                $attempts[] = $this->attempt($method, 0, 0, false, false, 'fetch budget exhausted');
                break;
            }

            $handler = $this->handlers[$method->value] ?? null;
            if (! $handler instanceof FetchMethodHandler || ! $handler->supports($method)) {
                $attempts[] = $this->attempt($method, 0, 0, false, false, 'handler not registered');
                continue;
            }

            $started = (int) round(microtime(true) * 1000);

            try {
                $result = $handler->fetch(
                    $url,
                    $this->boundedProfile($profile, $method, $budget),
                    $method,
                    $this->boundedOptions($options, $method, $budget),
                );
                $result = $this->applyReadyMarker($result, $readyMarkers);
            } catch (Throwable $exception) {
                $elapsed = (int) round(microtime(true) * 1000) - $started;
                $attempts[] = $this->attempt($method, $elapsed, 0, false, false, $exception->getMessage());
                continue;
            }

            $attempts[] = $this->attempt(
                $method,
                $result->elapsedMs,
                $result->status,
                $result->challengeDetected,
                $result->ok,
                $result->error,
            );
            $last = $result->withAttempts($attempts);
            $sawChallenge = $sawChallenge || $result->challengeDetected;

            $terminal = TerminalStatus::fromResult($result, $profile->soft404Markers);
            if ($terminal !== null) {
                throw new CrawlFetchException(
                    message: 'Terminal fetch status for URL [' . $url . '].',
                    errorCode: $terminal->code,
                    retryable: $terminal->retryable,
                    retryAfterSeconds: $terminal->retryAfterSeconds,
                    fetch: $last->toMeta(),
                );
            }

            if ($result->ok) {
                if ($profile->cookieHandoffAfterBrowser && $result->cookies !== []) {
                    $host = parse_url($result->finalUrl ?? $url, PHP_URL_HOST);
                    if (is_string($host) && $host !== '') {
                        $this->cookies->put($host, $result->cookies);
                    }
                }

                return $last;
            }
        }

        $lastAttempt = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $budgetExhausted = is_array($lastAttempt) && ($lastAttempt['error'] ?? null) === 'fetch budget exhausted';
        if ($budget->remainingSeconds() <= 0.0 || $budgetExhausted) {
            throw new CrawlFetchException(
                message: 'Fetch budget exhausted for URL [' . $url . '].',
                errorCode: CrawlErrorCode::Timeout,
                retryable: true,
                retryAfterSeconds: null,
                fetch: $last?->toMeta(),
            );
        }

        $code = $sawChallenge ? CrawlErrorCode::Challenge : CrawlErrorCode::Network;

        throw new CrawlFetchException(
            message: 'All fetch methods exhausted for URL [' . $url . '].',
            errorCode: $code,
            retryable: $code->defaultRetryable(),
            retryAfterSeconds: null,
            fetch: $last?->toMeta() ?? new \JOOservices\CrawlerX\Dto\FetchMetaDto(
                methodUsed: ($plan[0] ?? FetchMethod::Http)->value,
                elapsedMs: 0,
                challengeDetected: $sawChallenge,
                attempts: $attempts,
            ),
        );
    }

    public function cookies(): CookieHandoffStore
    {
        return $this->cookies;
    }

    /**
     * @return array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}
     */
    private function attempt(
        FetchMethod $method,
        int $elapsedMs,
        int $status,
        bool $challenge,
        bool $ok,
        ?string $error,
    ): array {
        $row = [
            'method' => $method->value,
            'elapsed_ms' => $elapsedMs,
            'status' => $status,
            'challenge' => $challenge,
            'ok' => $ok,
        ];

        if ($error !== null && $error !== '') {
            $row['error'] = $error;
        }

        return $row;
    }

    private function boundedProfile(SiteProfileDto $profile, FetchMethod $method, FetchBudget $budget): SiteProfileDto
    {
        if (
            $profile->playwright === null
            || ! in_array(
                $method,
                [
                    FetchMethod::Playwright,
                    FetchMethod::PlaywrightStealth,
                    FetchMethod::ChromeStealth,
                    FetchMethod::PuppeteerStealth,
                ],
                true,
            )
        ) {
            return $profile;
        }

        $playwright = $profile->playwright;
        $timeoutMs = min($playwright->navigationTimeoutMs, $budget->timeoutSeconds($method) * 1000);

        return new SiteProfileDto(
            slug: $profile->slug,
            displayName: $profile->displayName,
            baseUrl: $profile->baseUrl,
            fetchProfile: $profile->fetchProfile,
            fetchChain: $profile->fetchChain,
            http: $profile->http,
            playwright: new PlaywrightProfileDto(
                browser: $playwright->browser,
                headless: $playwright->headless,
                postWaitMs: min($playwright->postWaitMs, max(0, ($budget->timeoutSeconds($method) - 1) * 1000)),
                navigationTimeoutMs: $timeoutMs,
                stealthEnabled: $playwright->stealthEnabled,
                viewport: $playwright->viewport,
                locale: $playwright->locale,
                timezoneId: $playwright->timezoneId,
                userAgent: $playwright->userAgent,
                storageStatePath: $playwright->storageStatePath,
            ),
            cookieHandoffAfterBrowser: $profile->cookieHandoffAfterBrowser,
            readyMarkers: $profile->readyMarkers,
            soft404Markers: $profile->soft404Markers,
        );
    }

    /** @param list<string> $readyMarkers */
    private function applyReadyMarker(FetchResultDto $result, array $readyMarkers): FetchResultDto
    {
        if (! $result->ok || $readyMarkers === [] || $result->status < 200 || $result->status >= 300) {
            return $result;
        }

        if (ChallengeDetector::isUsableBody($result->body, $result->status, $readyMarkers)) {
            return $result;
        }

        return new FetchResultDto(
            ok: false,
            body: $result->body,
            status: $result->status,
            methodUsed: $result->methodUsed,
            elapsedMs: $result->elapsedMs,
            challengeDetected: false,
            finalUrl: $result->finalUrl,
            attempts: $result->attempts,
            cookies: $result->cookies,
            headers: $result->headers,
            error: 'ready marker missing',
        );
    }

    private function boundedOptions(?CrawlOptionsDto $options, FetchMethod $method, FetchBudget $budget): ?CrawlOptionsDto
    {
        if (! in_array($method, [FetchMethod::Http, FetchMethod::CurlImpersonate, FetchMethod::Flaresolverr], true)) {
            return $options;
        }

        $http = $options?->http;
        $timeout = $budget->timeoutSeconds($method);
        if ($http?->timeout !== null) {
            $timeout = min($timeout, $http->timeout);
        }

        return new CrawlOptionsDto(
            http: new HttpOptionsDto(
                timeout: $timeout,
                verifySsl: $http?->verifySsl,
                headers: $http?->headers,
            ),
            fetch: $options?->fetch,
        );
    }
}
