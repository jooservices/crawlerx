<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;

final readonly class UrlDetectionResult
{
    public function __construct(
        public bool $hostMatched,
        public bool $matched,
        public ?CrawlType $crawlType = null,
        public ?ImportEntity $entity = null,
        public ?string $urlType = null,
        public ?string $normalizedUrl = null,
    ) {
    }

    public static function noHostMatch(): self
    {
        return new self(hostMatched: false, matched: false);
    }

    public static function unrecognized(): self
    {
        return new self(hostMatched: true, matched: false);
    }
}
