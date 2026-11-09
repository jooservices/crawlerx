<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Attempt;

use JOOservices\CrawlerX\Dto\FetchResultDto;

/**
 * What one fetch has learned so far while the chain walks its methods.
 */
final class FetchRun
{
    /** @var list<array{started_at?: string, duration_ms?: int, http_status?: ?int, cf_mitigated?: ?string, outcome?: string, error_code?: ?string, method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}> */
    public array $attempts = [];

    public ?FetchResultDto $last = null;

    public bool $nonTerminalFailure = false;

    public bool $sawChallenge = false;

    /** A method got a real HTTP answer from the site that was not a challenge. */
    public bool $answered = false;
}
