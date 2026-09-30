<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

final class UnsupportedUrlException extends AbstractCrawlerException
{
    public function __construct(string $url)
    {
        parent::__construct("Unable to classify crawl URL [{$url}].");
    }
}
