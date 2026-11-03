<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Attributes\Hidden;
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
        #[Hidden]
        public readonly ?FetchMetaDto $fetch = null,
    ) {
    }
    public function withFetch(FetchMetaDto $fetch): self
    {
        return new self($this->url, $this->page, $this->entityType, $this->items, $this->pagination, $fetch);
    }
}
