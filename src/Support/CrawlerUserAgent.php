<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

final class CrawlerUserAgent
{
    private const DEFAULT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    public static function fromEnvironment(): string
    {
        $configured = getenv('CRAWLERX_USER_AGENT');

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : self::DEFAULT;
    }
}
