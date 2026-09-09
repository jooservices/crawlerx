<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class CrawlListResultDto extends Dto
{
    /**
     * @param  list<CrawlItemResultDto>  $items
     */
    public function __construct(
        public readonly string $url,
        public readonly int $page,
        #[MapTo('entity_type')]
        public readonly string $entityType,
        public readonly array $items,
        public readonly CrawlPaginationDto $pagination,
    ) {
    }
}
