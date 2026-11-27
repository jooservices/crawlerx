<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use Throwable;

class CrawlBlockedException extends AbstractCrawlerException
{
    public function __construct(
        string $message,
        public readonly ?FetchMetaDto $fetch = null,
        int $code = 0,
        ?Throwable $previous = null,
        /** @var list<array<string, mixed>> $attempts attempts the adapter itself made, added to those of the page fetch */
        public readonly array $attempts = [],
    ) {
        parent::__construct($message, $code, $previous);
    }
}
