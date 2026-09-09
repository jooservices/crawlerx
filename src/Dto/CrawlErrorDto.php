<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\Dto\Core\Dto;

final class CrawlErrorDto extends Dto
{
    public function __construct(
        public readonly CrawlErrorCode $code,
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly ?FetchMetaDto $fetch = null,
    ) {
    }
}
