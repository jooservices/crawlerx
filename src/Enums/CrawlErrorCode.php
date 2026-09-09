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
}
