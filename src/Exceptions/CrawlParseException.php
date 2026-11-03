<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Dto\FetchMetaDto;
use Throwable;

final class CrawlParseException extends AbstractCrawlerException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, public readonly ?FetchMetaDto $fetch = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
