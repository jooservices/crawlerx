<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Enums;

enum FetchProfile: string
{
    case HttpOnly = 'http_only';
    case BrowserLikely = 'browser_likely';
    case Adaptive = 'adaptive';

    /**
     * @return list<FetchMethod>
     */
    public function chain(): array
    {
        return match ($this) {
            self::HttpOnly => FetchMethod::httpOnlyChain(),
            self::BrowserLikely => FetchMethod::browserChain(),
            self::Adaptive => FetchMethod::defaultChain(),
        };
    }
}
