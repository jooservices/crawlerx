<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use RuntimeException;

final class UnsupportedUrlException extends RuntimeException
{
    public function __construct(string $url)
    {
        parent::__construct("Unable to classify crawl URL [{$url}].");
    }
}
