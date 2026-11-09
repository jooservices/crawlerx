<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\LiveCheck;

use RuntimeException;
use Throwable;

final class LiveCheckRequestLimitException extends RuntimeException
{
    public function __construct(public readonly string $reason = 'request_limit', ?Throwable $previous = null)
    {
        parent::__construct('The live-check sample could not be checked.', 0, $previous);
    }
}
