<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use JOOservices\CrawlerX\Dto\FetchResultDto;

/**
 * Tells the host circuit breaker how a fetch ended. Does nothing without a breaker or a host.
 */
final class HostCircuitReporter
{
    public function __construct(private readonly ?HostCircuit $circuit = null)
    {
    }

    public function success(?string $host): void
    {
        if ($host !== null) {
            $this->circuit?->recordSuccess($host);
        }
    }

    public function failure(?string $host): void
    {
        if ($host !== null) {
            $this->circuit?->recordFailure($host);
        }
    }

    /** A terminal status counts as host success, except a throttle answer after earlier failures. */
    public function terminal(FetchResultDto $result, bool $nonTerminalFailure, ?string $host): void
    {
        $guard = $result->headers['X-CrawlerX-Guard'] ?? null;
        if ($guard === null) {
            $this->success($host);
        } elseif (($guard[0] ?? null) === 'throttle' && $nonTerminalFailure) {
            $this->failure($host);
        }
    }
}
