<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use Closure;
use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Contracts\LoginCookieProvider;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Attempt\AttemptRow;
use JOOservices\CrawlerX\Fetch\Attempt\FetchRun;
use JOOservices\CrawlerX\Fetch\Attempt\MethodAttemptRunner;
use JOOservices\CrawlerX\Fetch\Budget\FetchBudget;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuit;
use JOOservices\CrawlerX\Fetch\Guard\HostCircuitReporter;
use JOOservices\CrawlerX\Fetch\Guard\HostThrottle;
use JOOservices\CrawlerX\Fetch\Guard\PreFetchGuard;
use JOOservices\CrawlerX\Fetch\Guard\RobotsGuard;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;
use JOOservices\CrawlerX\Fetch\Outcome\FetchRejections;
use JOOservices\CrawlerX\Fetch\Request\AuthWallDetector;
use JOOservices\CrawlerX\Fetch\Request\FetchRequest;
use JOOservices\CrawlerX\Fetch\Session\CookieHandoffStore;
use JOOservices\CrawlerX\Fetch\Session\SessionStore;

final class FetchFallbackChain
{
    private readonly SessionStore $sessions;

    private readonly HostCircuitReporter $circuit;

    /** @var Closure(int): void */
    private readonly Closure $retrySleeper;

    /**
     * @param  array<string, FetchMethodHandler>  $handlers
     * @param  Closure(int): void|null  $retrySleeper
     */
    public function __construct(
        private readonly array $handlers,
        private readonly CookieHandoffStore $cookies = new CookieHandoffStore(),
        ?SessionStore $sessions = null,
        private readonly ?LoginCookieProvider $logins = null,
        private readonly ?FetchRuntimeConfig $runtime = null,
        ?Closure $retrySleeper = null,
        private readonly ?HostThrottle $hostThrottle = null,
        private readonly ?HostCircuit $hostCircuit = null,
        private readonly ?RobotsGuard $robotsGuard = null,
        private readonly int $maxTransientRetries = TransientRetry::MAX_RETRIES,
        /** @var Closure(): (int|float)|null */
        private readonly ?Closure $budgetClock = null,
        /** @var Closure(string): bool|null */
        private readonly ?Closure $beforeOutboundRequest = null,
    ) {
        $this->sessions = $sessions ?? new SessionStore();
        $this->circuit = new HostCircuitReporter($hostCircuit);
        $this->retrySleeper = $retrySleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    public function cookies(): CookieHandoffStore
    {
        return $this->cookies;
    }

    /** Runs the ordered fetch methods and returns the first acceptable result.
     *
     * @param  list<FetchMethod>  $plan
     */
    public function fetch(
        string $url,
        SiteProfileDto $profile,
        array $plan,
        ?CrawlOptionsDto $options = null,
        ?CrawlType $type = null,
    ): FetchResultDto {
        $parsedHost = parse_url($url, PHP_URL_HOST);
        $host = is_string($parsedHost) && $parsedHost !== '' ? $parsedHost : null;
        $run = new FetchRun();
        $budget = FetchBudget::start($options?->fetch, $this->budgetClock);
        $session = $this->sessions->get($profile->slug);
        $request = new FetchRequest(
            $url,
            $profile,
            $options,
            $profile->readyMarkersFor($type),
            $session,
            $this->logins?->cookiesFor($profile->slug) ?? [],
            $session['userAgent'] ?? $this->defaultUserAgent($profile),
        );

        $robotsDelaySeconds = null;
        if ($this->robotsGuard !== null && $this->respectsRobots($profile)) {
            $this->robotsGuard->assertAllowed($url, $request->userAgent ?? '');
            $robotsDelaySeconds = $this->robotsGuard->crawlDelaySeconds($url, $request->userAgent ?? '');
        }

        $runner = new MethodAttemptRunner(
            new PreFetchGuard($this->hostThrottle, $this->hostCircuit, $robotsDelaySeconds),
            $this->retrySleeper,
            $this->maxTransientRetries,
            $this->beforeOutboundRequest,
        );

        foreach ($plan as $method) {
            // FlareSolverr is the expensive last resort. It is skipped only when an earlier method got a real
            // answer from the site (an HTTP status) that was not a challenge. When nothing answered (a method
            // could not run, or a browser timed out on a page that never settles, as Cloudflare interstitials
            // do) it must still be tried, otherwise such sites report a false network error.
            if ($method === FetchMethod::Flaresolverr && ! $run->sawChallenge && $run->answered) {
                $run->attempts[] = AttemptRow::build($method, 0, 0, false, false, 'earlier non-challenge answer', skipped: true);
                continue;
            }

            if (! $budget->canStart($method)) {
                $run->attempts[] = AttemptRow::build($method, 0, 0, false, false, 'fetch budget exhausted', skipped: true);
                break;
            }

            $handler = $this->handlers[$method->value] ?? null;
            if (! $handler instanceof FetchMethodHandler || ! $handler->supports($method)) {
                $run->attempts[] = AttemptRow::build($method, 0, 0, false, false, 'handler not registered', skipped: true);
                continue;
            }

            $result = $runner->run($handler, $method, $request, $budget, $run);
            if ($result === null) {
                continue;
            }

            $run->last = $result->withAttempts($run->attempts);
            $run->sawChallenge = $run->sawChallenge || $result->challengeDetected;
            $this->rejectIfFinal($result, $run->last, $request->url, $host);

            if ($result->challengeDetected && $request->session !== null) {
                $this->sessions->forget($profile->slug);
                $request = $request->withoutSession($this->defaultUserAgent($profile));
            }

            $terminal = TerminalStatus::fromResult($result, $profile->soft404Markers);
            if ($terminal !== null) {
                $this->circuit->terminal($result, $run->nonTerminalFailure, $host);

                throw FetchRejections::terminal($url, $terminal, $run->last);
            }

            if ($result->ok && $budget->remainingSeconds() <= 0.0) {
                $run->attempts = FetchRejections::rejectedMeta($run->last, CrawlErrorCode::Timeout)->attempts;
                break;
            }
            if ($result->ok) {
                $this->circuit->success($host);
                $this->rememberSuccess($result, $request);

                return $run->last;
            }

            $run->nonTerminalFailure = true;
        }

        $this->recordExhausted($run, $request, $host);

        throw FetchRejections::exhausted($url, $run->last, $run->attempts, $plan, $budget, $run->sawChallenge);
    }

    /** SSRF blocks and login walls end the fetch at once. */
    private function rejectIfFinal(FetchResultDto $result, FetchResultDto $last, string $url, ?string $host): void
    {
        if ($result->error === CrawlErrorCode::SsrfBlocked->value) {
            throw FetchRejections::ssrfBlocked($result, $last);
        }

        if (AuthWallDetector::isAuthRequired($result)) {
            $this->circuit->success($host);

            throw FetchRejections::authRequired($url, $last);
        }
    }

    private function recordExhausted(FetchRun $run, FetchRequest $request, ?string $host): void
    {
        if ($run->nonTerminalFailure) {
            $this->circuit->failure($host);
        }

        if ($run->sawChallenge) {
            $this->sessions->recordChallenge($request->profile->slug, $request->userAgent, $this->runtime->userAgentPool ?? []);
        }
    }

    private function rememberSuccess(FetchResultDto $result, FetchRequest $request): void
    {
        $this->sessions->putResult($request->profile->slug, $result);
        if ($request->profile->cookieHandoffAfterBrowser && $result->cookies !== []) {
            $host = parse_url($result->finalUrl ?? $request->url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $this->cookies->put($host, $result->cookies);
            }
        }
    }

    private function defaultUserAgent(SiteProfileDto $profile): ?string
    {
        return $this->runtime->userAgent
            ?? $profile->playwright->userAgent
            ?? $profile->http->headers['User-Agent']
            ?? null;
    }

    private function respectsRobots(SiteProfileDto $profile): bool
    {
        if ($profile->respectRobots !== null) {
            return $profile->respectRobots;
        }

        return $this->runtime === null || $this->runtime->respectRobots;
    }
}
