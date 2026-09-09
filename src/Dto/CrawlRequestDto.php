<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\Dto\Core\Dto;

final class CrawlRequestDto extends Dto
{
    public function __construct(
        public readonly string $url,
        public readonly CrawlType $type = CrawlType::Listing,
        public readonly int $page = 1,
        public readonly ?CrawlOptionsDto $options = null,
        public readonly ?FetchResultDto $fetch = null,
    ) {
    }
}
