<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Attributes\Hidden;
use JOOservices\Dto\Core\Dto;

final class CrawlItemResultDto extends Dto
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $url,
        #[MapTo('entity_type')]
        public readonly string $entityType,
        public readonly array $meta = [],
        #[MapTo('next_crawl_type')]
        public readonly ?string $nextCrawlType = null,
        #[Hidden]
        public readonly ?FetchMetaDto $fetch = null,
    ) {
    }

    /**
     * A non-null next crawl type means this URL is an intermediate hop that
     * must be crawled again with that type before reaching a terminal item.
     * Null means the item is terminal (e.g. a detail page).
     */
    public function withNextCrawlType(?string $nextCrawlType): self
    {
        return new self(
            url: $this->url,
            entityType: $this->entityType,
            meta: $this->meta,
            nextCrawlType: $nextCrawlType,
            fetch: $this->fetch,
        );
    }
    public function withFetch(FetchMetaDto $fetch): self
    {
        return new self($this->url, $this->entityType, $this->meta, $this->nextCrawlType, $fetch);
    }
}
