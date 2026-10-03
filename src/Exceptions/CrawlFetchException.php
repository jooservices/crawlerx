<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use Throwable;

final class CrawlFetchException extends CrawlBlockedException
{
    public function __construct(
        string $message,
        public readonly CrawlErrorCode $errorCode,
        public readonly bool $retryable,
        public readonly ?int $retryAfterSeconds,
        ?FetchMetaDto $fetch = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $fetch, $code, $previous);
    }
}
