<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class CrawlOptionsDto extends Dto
{
    public function __construct(
        public readonly ?HttpOptionsDto $http = null,
        public readonly ?FetchOptionsDto $fetch = null,
    ) {
    }
}
