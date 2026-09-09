<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use RuntimeException;

final class AmbiguousUrlException extends RuntimeException
{
    public function __construct(string $url)
    {
        parent::__construct("Crawl URL [{$url}] matched a site but not a crawl type. Pass ->type().");
    }
}
