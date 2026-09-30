<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

final class AdapterNotFoundException extends AbstractCrawlerException
{
    public function __construct(string $name)
    {
        parent::__construct("Adapter [{$name}] is not registered.");
    }
}
