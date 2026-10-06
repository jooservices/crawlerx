<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

final class LoginCookiePolicy
{
    public static function isRequired(string $site): bool
    {
        return $site === 'avfan';
    }
}
