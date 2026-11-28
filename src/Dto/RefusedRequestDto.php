<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

/** A request an adapter sent itself that the site refused or answered with nothing: what the fetch meta needs to record it. */
final class RefusedRequestDto extends Dto
{
    /**
     * @param  int  $status  the HTTP status of the answer
     * @param  string  $reason  the cause in a few words, for example "HTTP 403" or "empty body"
     * @param  int  $startedAt  when the request was sent, in milliseconds since the epoch
     */
    public function __construct(
        public readonly int $status,
        public readonly string $reason,
        public readonly int $startedAt,
    ) {
    }
}
