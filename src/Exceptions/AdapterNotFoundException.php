<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use RuntimeException;

final class AdapterNotFoundException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Adapter [{$name}] is not registered.");
    }
}
