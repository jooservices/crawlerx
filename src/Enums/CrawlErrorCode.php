<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Enums;

enum CrawlErrorCode: string
{
    case UnsupportedUrl = 'unsupported_url';
    case AmbiguousUrl = 'ambiguous_url';
    case AdapterNotFound = 'adapter_not_found';
    case Blocked = 'blocked';
    case ParseFailed = 'parse_failed';
    case Unknown = 'unknown';
    case NotFound = 'not_found';
    case Gone = 'gone';
    case RateLimited = 'rate_limited';
    case Timeout = 'timeout';
    case Challenge = 'challenge';
    case Network = 'network';
    case AuthRequired = 'auth_required';

    public function defaultRetryable(): bool
    {
        return match ($this) {
            self::RateLimited, self::Timeout, self::Challenge, self::Network => true,
            default => false,
        };
    }
}
