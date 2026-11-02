<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class CrawlPaginationDto extends Dto
{
    public function __construct(
        public readonly ?int $currentPage,
        public readonly ?int $lastPage,
        public readonly ?int $nextPage,
        public readonly ?string $nextUrl,
        public readonly bool $hasNextPage,
    ) {
    }
}
