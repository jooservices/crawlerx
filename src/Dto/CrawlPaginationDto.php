<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;
use JOOservices\Dto\Attributes\Hidden;

final class CrawlPaginationDto extends Dto
{
    public function __construct(
        public readonly ?int $currentPage,
        public readonly ?int $lastPage,
        public readonly ?int $nextPage,
        public readonly ?string $nextUrl,
        public readonly bool $hasNextPage,
        #[Hidden]
        public readonly ?int $totalRows = null,
    ) {
    }
}
