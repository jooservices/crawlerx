<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use JOOservices\CrawlerX\Dto\RefusedRequestDto;
use Throwable;

class CrawlBlockedException extends AbstractCrawlerException
{
    public function __construct(
        string $message,
        public readonly ?FetchMetaDto $fetch = null,
        int $code = 0,
        ?Throwable $previous = null,
        /** The request the adapter sent itself that was refused, recorded next to the attempts of the page fetch. */
        public readonly ?RefusedRequestDto $refused = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
