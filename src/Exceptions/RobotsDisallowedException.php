<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

final class RobotsDisallowedException extends CrawlBlockedException
{
    public function __construct(string $url, ?string $rule = null)
    {
        $detail = $rule !== null && $rule !== '' ? " (matched rule [{$rule}])" : '';

        parent::__construct("robots.txt disallows crawling URL [{$url}]{$detail}.");
    }
}
