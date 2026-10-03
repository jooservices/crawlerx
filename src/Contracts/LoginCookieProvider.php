<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

interface LoginCookieProvider
{
    /** @return array<string, string> */
    public function cookiesFor(string $site): array;
}
