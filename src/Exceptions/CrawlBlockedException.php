<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use RuntimeException;
use Throwable;

final class CrawlBlockedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?FetchMetaDto $fetch = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
