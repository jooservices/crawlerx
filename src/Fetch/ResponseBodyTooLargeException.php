<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use RuntimeException;

final class ResponseBodyTooLargeException extends RuntimeException
{
    public function __construct(int $maximumBytes)
    {
        parent::__construct(sprintf('Response body exceeds the %d byte limit.', $maximumBytes));
    }
}
