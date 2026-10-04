<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class CrawlOptionsDto extends Dto
{
    /**
     * @param  array<string, mixed>|null  $storageState
     * @param  list<string>  $readyMarkers
     */
    public function __construct(
        public readonly ?HttpOptionsDto $http = null,
        public readonly ?FetchOptionsDto $fetch = null,
        public readonly ?int $methodTimeoutSeconds = null,
        public readonly ?array $storageState = null,
        public readonly array $readyMarkers = [],
    ) {
    }
}
