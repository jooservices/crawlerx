<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

final class AmbiguousUrlException extends AbstractCrawlerException
{
    public function __construct(string $url)
    {
        parent::__construct("Crawl URL [{$url}] matched a site but not a crawl type. Pass ->type().");
    }
}
