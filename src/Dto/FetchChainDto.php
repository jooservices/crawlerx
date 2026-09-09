<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\Dto\Core\Dto;

final class FetchChainDto extends Dto
{
    /**
     * @param  list<FetchMethod>  $methods
     */
    public function __construct(
        public readonly array $methods,
    ) {
    }
}
