<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use Closure;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;
use Throwable;

final class FetchFallbackChain
{
    private readonly SessionStore $sessions;

    private readonly ?LoginCookieProvider $logins;

    private readonly ?FetchRuntimeConfig $runtime;

    /** @var Closure(int): void */
    private readonly Closure $retrySleeper;

    private readonly ?HostThrottle $hostThrottle;

    /**
     * @param  array<string, FetchMethodHandler>  $handlers
     * @param  Closure(int): void|null  $retrySleeper
     */
    public function __construct(
        private readonly array $handlers,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        ?SessionStore $sessions = null,
        ?LoginCookieProvider $logins = null,
        ?FetchRuntimeConfig $runtime = null,
        ?Closure $retrySleeper = null,
        ?HostThrottle $hostThrottle = null,
    ) {
        $this->sessions = $sessions ?? new SessionStore();
        $this->logins = $logins;
        $this->runtime = $runtime;
        $this->retrySleeper = $retrySleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
        $this->hostThrottle = $hostThrottle;
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
        $transientRetry = new TransientRetry();
        $readyMarkers = $profile->readyMarkersFor($type);
        $session = $this->sessions->get($profile->slug);
        $loginCookies = $this->logins?->cookiesFor($profile->slug) ?? [];
        $requestUserAgent = $session['userAgent']
            ?? $this->runtimeUserAgent()
            ?? $this->profileUserAgent($profile)
            ?? $profile->http->headers['User-Agent']
            ?? null;

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
                $result = $this->fetchWithHandler(
                    $handler,
                    $url,
                    $profile,
                    $options,
                    $method,
                    $budget,
                    $readyMarkers,
                    $session,
                    $loginCookies,
                    $requestUserAgent,
                );
            } catch (Throwable $exception) {
                $elapsed = (int) round(microtime(true) * 1000) - $started;
                $attempts[] = $this->attempt($method, $elapsed, 0, false, false, TransientRetry::errorCode($exception));
                continue;
            }

            $retries = 0;
            while (true) {
                $attempts[] = $this->attempt(
                    $method,
                    $result->elapsedMs,
                    $result->status,
                    $result->challengeDetected,
                    $result->ok,
                    $result->error,
                );

                if (
                    $method !== FetchMethod::Http
                    || ! $transientRetry->canRetry($result, $retries)
                ) {
                    break;
                }

                $delay = $transientRetry->delaySeconds($retries + 1);
                if (! $transientRetry->canAfford($budget->remainingSeconds(), $delay)) {
                    break;
                }

                ($this->retrySleeper)((int) round($delay * 1_000_000));
                if (! $budget->canStart($method)) {
                    break;
                }

                $started = (int) round(microtime(true) * 1000);
                try {
                    $result = $this->fetchWithHandler(
                        $handler,
                        $url,
                        $profile,
                        $options,
                        $method,
                        $budget,
                        $readyMarkers,
                        $session,
                        $loginCookies,
                        $requestUserAgent,
                    );
                } catch (Throwable $exception) {
                    $elapsed = (int) round(microtime(true) * 1000) - $started;
                    $attempts[] = $this->attempt($method, $elapsed, 0, false, false, TransientRetry::errorCode($exception));
                    break;
                }

                $retries++;
            }

            $last = $result->withAttempts($attempts);
            $sawChallenge = $sawChallenge || $result->challengeDetected;

            if ($result->error === CrawlErrorCode::SsrfBlocked->value) {
                throw new CrawlFetchException(
                    message: 'Fetch blocked by SSRF policy for URL [' . $url . '].',
                    errorCode: CrawlErrorCode::SsrfBlocked,
                    retryable: false,
                    retryAfterSeconds: null,
                    fetch: $last->toMeta(),
                );
            }

            if ($this->isAuthRequired($result)) {
                throw new CrawlFetchException(
                    message: 'Authentication is required for URL [' . $url . '].',
                    errorCode: CrawlErrorCode::AuthRequired,
                    retryable: false,
                    retryAfterSeconds: null,
                    fetch: $last->toMeta(),
                );
            }

            if ($result->challengeDetected && $session !== null) {
                $this->sessions->forget($profile->slug);
                $session = null;
                $requestUserAgent = $this->runtimeUserAgent()
                    ?? $this->profileUserAgent($profile)
                    ?? $profile->http->headers['User-Agent']
                    ?? null;
            }

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
                $this->sessions->putResult($profile->slug, $result);
                if ($profile->cookieHandoffAfterBrowser && $result->cookies !== []) {
                    $host = parse_url($result->finalUrl ?? $url, PHP_URL_HOST);
                    if (is_string($host) && $host !== '') {
                        $this->cookies->put($host, $result->cookies);
                    }
                }

                return $last;
            }
        }

        if ($sawChallenge) {
            $this->sessions->recordChallenge(
                $profile->slug,
                $requestUserAgent,
                $this->runtimeUserAgentPool(),
            );
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

    /**
     * @param  array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}|null  $session
     * @param  array<string, string>  $loginCookies
     * @param  list<string>  $readyMarkers
     */
    private function fetchWithHandler(
        FetchMethodHandler $handler,
        string $url,
        SiteProfileDto $profile,
        ?CrawlOptionsDto $options,
        FetchMethod $method,
        FetchBudget $budget,
        array $readyMarkers,
        ?array $session,
        array $loginCookies,
        ?string $requestUserAgent,
    ): FetchResultDto {
        $host = parse_url($url, PHP_URL_HOST);
        if ($this->hostThrottle !== null && is_string($host) && $host !== '') {
            $retryAfter = $this->hostThrottle->waitBeforeFetch(
                $host,
                $profile->defaultThrottle,
                $budget,
                $method,
            );
            if ($retryAfter !== null) {
                return new FetchResultDto(
                    ok: false,
                    body: '',
                    status: 429,
                    methodUsed: $method,
                    elapsedMs: 0,
                    challengeDetected: false,
                    finalUrl: $url,
                    headers: ['Retry-After' => [(string) $retryAfter]],
                    error: CrawlErrorCode::RateLimited->value,
                );
            }
        }

        $result = $handler->fetch(
            $url,
            $this->boundedProfile(
                $this->profileWithSession($profile, $options, $session, $loginCookies, $requestUserAgent),
                $method,
                $budget,
            ),
            $method,
            $this->boundedOptions(
                $options,
                $method,
                $budget,
                $readyMarkers,
                $session['storageState'] ?? null,
            ),
        );

        return $this->applyReadyMarker($result, $readyMarkers);
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
                readyTimeoutMs: min($playwright->readyTimeoutMs, max(1, ($budget->timeoutSeconds($method) - 1) * 1000)),
                navigationTimeoutMs: $timeoutMs,
                stealthEnabled: $playwright->stealthEnabled,
                blockResources: $playwright->blockResources,
                viewport: $playwright->viewport,
                locale: $playwright->locale,
                timezoneId: $playwright->timezoneId,
                userAgent: $playwright->userAgent,
                storageStatePath: $playwright->storageStatePath,
            ),
            cookieHandoffAfterBrowser: $profile->cookieHandoffAfterBrowser,
            readyMarkers: $profile->readyMarkers,
            soft404Markers: $profile->soft404Markers,
            defaultThrottle: $profile->defaultThrottle,
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

    /**
     * @param  list<string>  $readyMarkers
     * @param  array<string, mixed>|null  $sessionStorageState
     */
    private function boundedOptions(
        ?CrawlOptionsDto $options,
        FetchMethod $method,
        FetchBudget $budget,
        array $readyMarkers = [],
        ?array $sessionStorageState = null,
    ): CrawlOptionsDto {
        $http = $options?->http;
        $timeout = $budget->timeoutSeconds($method);
        if ($http?->timeout !== null) {
            $timeout = min($timeout, $http->timeout);
        }

        $httpOptions = in_array($method, [FetchMethod::Http, FetchMethod::CurlImpersonate, FetchMethod::Flaresolverr], true)
            ? new HttpOptionsDto(
                timeout: $timeout,
                verifySsl: $http?->verifySsl,
                headers: $http?->headers,
            )
            : $http;

        /** @var array<string, mixed>|null $storageState */
        $storageState = $options === null ? $sessionStorageState : ($options->storageState ?? $sessionStorageState);

        return new CrawlOptionsDto(
            http: $httpOptions,
            fetch: $options?->fetch,
            methodTimeoutSeconds: $budget->timeoutSeconds($method),
            storageState: $storageState,
            readyMarkers: $readyMarkers !== [] ? $readyMarkers : ($options !== null ? $options->readyMarkers : []),
        );
    }

    /**
     * @param  array{cookies: array<string, string>, userAgent: ?string, source: string, expiresAt: int, storageState: array<string, mixed>|null, challengeCount: int}|null  $session
     * @param  array<string, string>  $loginCookies
     */
    private function profileWithSession(
        SiteProfileDto $profile,
        ?CrawlOptionsDto $options,
        ?array $session,
        array $loginCookies,
        ?string $requestUserAgent,
    ): SiteProfileDto {
        $headers = $profile->http->headers;
        if ($options?->http?->headers !== null) {
            $headers = array_merge($headers, $options->http->headers);
        }

        $sessionCookies = $session['cookies'] ?? [];
        $cookies = array_merge($this->cookieHeaderToMap($headers['Cookie'] ?? null), $sessionCookies, $loginCookies);
        if ($cookies !== []) {
            $headers['Cookie'] = $this->cookieMapToHeader($cookies);
        }

        $userAgent = $session['userAgent'] ?? $requestUserAgent;
        if ($userAgent !== null && $userAgent !== '') {
            $headers['User-Agent'] = $userAgent;
        }

        $playwright = $profile->playwright;
        if ($playwright !== null && $userAgent !== null && $userAgent !== '') {
            $playwright = new PlaywrightProfileDto(
                browser: $playwright->browser,
                headless: $playwright->headless,
                postWaitMs: $playwright->postWaitMs,
                navigationTimeoutMs: $playwright->navigationTimeoutMs,
                stealthEnabled: $playwright->stealthEnabled,
                viewport: $playwright->viewport,
                locale: $playwright->locale,
                timezoneId: $playwright->timezoneId,
                userAgent: $userAgent,
                storageStatePath: $playwright->storageStatePath,
                readyTimeoutMs: $playwright->readyTimeoutMs,
                blockResources: $playwright->blockResources,
            );
        }

        return new SiteProfileDto(
            slug: $profile->slug,
            displayName: $profile->displayName,
            baseUrl: $profile->baseUrl,
            fetchProfile: $profile->fetchProfile,
            fetchChain: $profile->fetchChain,
            http: new HttpProfileDto($profile->http->timeout, $profile->http->verifySsl, $headers),
            playwright: $playwright,
            cookieHandoffAfterBrowser: $profile->cookieHandoffAfterBrowser,
            readyMarkers: $profile->readyMarkers,
            soft404Markers: $profile->soft404Markers,
            defaultThrottle: $profile->defaultThrottle,
        );
    }

    /** @return array<string, string> */
    private function cookieHeaderToMap(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $cookies = [];
        foreach (explode(';', $header) as $part) {
            $separator = strpos($part, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($part, 0, $separator));
            $value = trim(substr($part, $separator + 1));
            if ($name !== '' && $value !== '') {
                $cookies[$name] = $value;
            }
        }

        return $cookies;
    }

    /** @param array<string, string> $cookies */
    private function cookieMapToHeader(array $cookies): string
    {
        return implode('; ', array_map(
            static fn(string $name, string $value): string => $name . '=' . $value,
            array_keys($cookies),
            array_values($cookies),
        ));
    }

    private function isAuthRequired(FetchResultDto $result): bool
    {
        if ($result->status === 401) {
            return true;
        }

        $visible = strtolower(trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags(preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1\s*>/is', ' ', $result->body) ?? $result->body), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        )));
        foreach (['login required', 'authentication required', 'please log in', 'please login', 'sign in required'] as $marker) {
            if (str_contains($visible, $marker)) {
                return true;
            }
        }

        $path = strtolower((string) parse_url($result->finalUrl ?? '', PHP_URL_PATH));

        return preg_match('~/(?:login|signin|sign-in)(?:/|$)~', $path) === 1;
    }

    private function runtimeUserAgent(): ?string
    {
        if ($this->runtime === null) {
            return null;
        }

        return $this->runtime->userAgent;
    }

    /** @return list<string> */
    private function runtimeUserAgentPool(): array
    {
        if ($this->runtime === null) {
            return [];
        }

        return $this->runtime->userAgentPool;
    }

    private function profileUserAgent(SiteProfileDto $profile): ?string
    {
        if ($profile->playwright === null) {
            return null;
        }

        return $profile->playwright->userAgent;
    }
}
