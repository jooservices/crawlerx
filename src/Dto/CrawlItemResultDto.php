<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Attributes\MapTo;
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
    ) {
    }
}
