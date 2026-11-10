<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Attempt;

use Closure;
use JOOservices\CrawlerX\Contracts\CacheAwareFetchMethodHandler;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\RobotsDisallowedException;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\PreFetchGuard;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use JOOservices\CrawlerX\Fetch\Request\BudgetBounds;
use JOOservices\CrawlerX\Fetch\Request\FetchRequest;
use JOOservices\CrawlerX\Fetch\Request\ReadyMarkerGate;
use JOOservices\CrawlerX\Fetch\Request\SessionProfile;
use Throwable;

/**
 * Runs one fetch method: pre-fetch guards, the handler call and transient HTTP retries with backoff.
 * One instance serves one fetch (it owns that fetch's guard state).
 */
final class MethodAttemptRunner
{
    private readonly TransientRetry $transientRetry;

    /**
     * @param  Closure(int): void  $retrySleeper
     */
    public function __construct(
        private readonly PreFetchGuard $guard,
        private readonly Closure $retrySleeper,
        private readonly int $maxTransientRetries,
        /** @var Closure(string): bool|null */
        private readonly ?Closure $beforeOutboundRequest = null,
    ) {
        $this->transientRetry = new TransientRetry();
    }

    /** The final result of the method, or null when the handler threw before producing any. */
    public function run(FetchMethodHandler $handler, FetchMethod $method, FetchRequest $request, FetchBudget $budget, FetchRun $run): ?FetchResultDto
    {
        $started = $this->nowMs();
        $result = $this->attemptOnce($handler, $method, $request, $budget, $run, $started);
        if ($result === null) {
            return null;
        }

        $retries = 0;
        while (true) {
            $run->attempts[] = AttemptRow::build(
                $method,
                $result->elapsedMs,
                $result->status,
                $result->challengeDetected,
                $result->ok,
                $result->error,
                started: $started,
                headers: $result->headers,
            );
            // Every answer counts, also one a later retry replaces (a 503 followed by a network error). Browser services report
            // a failed navigation as a small non-HTTP code (for example 1), which is not an answer from the site.
            $run->answered = $run->answered || ($result->status >= 100 && ! $result->challengeDetected);

            if (
                $method !== FetchMethod::Http
                || $retries >= $this->maxTransientRetries
                || ! $this->transientRetry->canRetry($result, $retries)
            ) {
                break;
            }

            $delay = $this->transientRetry->delaySeconds($retries + 1);
            if (! $this->transientRetry->canAfford($budget->remainingSeconds(), $delay)) {
                break;
            }

            ($this->retrySleeper)((int) round($delay * 1_000_000));
            if (! $budget->canStart($method)) {
                break;
            }

            $started = $this->nowMs();
            $retried = $this->attemptOnce($handler, $method, $request, $budget, $run, $started);
            if ($retried === null) {
                break;
            }

            $result = $retried;
            $retries++;
        }

        return $result;
    }

    /** One handler call; a thrown error is logged as a failed attempt and yields null. */
    private function attemptOnce(FetchMethodHandler $handler, FetchMethod $method, FetchRequest $request, FetchBudget $budget, FetchRun $run, int $started): ?FetchResultDto
    {
        try {
            return $this->fetchWithHandler($handler, $method, $request, $budget);
        } catch (Throwable $exception) {
            if ($exception instanceof RobotsDisallowedException) {
                throw $exception;
            }

            $run->attempts[] = AttemptRow::build($method, $this->nowMs() - $started, 0, false, false, TransientRetry::errorCode($exception), started: $started);
            $run->nonTerminalFailure = true;

            return null;
        }
    }

    /** Runs one fetch method after guards and applies the response's ready-marker check. */
    private function fetchWithHandler(FetchMethodHandler $handler, FetchMethod $method, FetchRequest $request, FetchBudget $budget): FetchResultDto
    {
        $profile = SessionProfile::apply($request->profile, $request->options, $request->session, $request->loginCookies, $request->userAgent);
        // A cache hit never reaches the host, so it skips the throttle and circuit guards.
        $cached = $handler instanceof CacheAwareFetchMethodHandler
            ? $handler->cachedResponse($request->url, $profile, $method, $request->options)
            : null;
        if ($cached !== null) {
            return ReadyMarkerGate::apply($cached, $request->readyMarkers);
        }

        $blocked = $this->guard->check($request->url, $request->profile, $method, $budget);
        if ($blocked !== null) {
            return $blocked;
        }

        if (
            $method !== FetchMethod::Http
            && $this->beforeOutboundRequest !== null
            && ! ($this->beforeOutboundRequest)($request->url)
        ) {
            return new FetchResultDto(
                ok: false,
                body: '',
                status: 429,
                methodUsed: $method,
                elapsedMs: 0,
                challengeDetected: false,
                finalUrl: $request->url,
                headers: [
                    'Retry-After' => ['0'],
                    'X-CrawlerX-Request-Limit' => ['exceeded'],
                ],
                error: CrawlErrorCode::RateLimited->value,
            );
        }

        $result = $handler->fetch(
            $request->url,
            BudgetBounds::profile($profile, $method, $budget),
            $method,
            BudgetBounds::options($request->options, $method, $budget, $request->readyMarkers, $request->session['storageState'] ?? null),
        );

        return ReadyMarkerGate::apply($result, $request->readyMarkers);
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
