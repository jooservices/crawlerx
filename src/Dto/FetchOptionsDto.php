<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\Dto\Core\Dto;

final class FetchOptionsDto extends Dto
{
    public function __construct(
        public readonly ?FetchProfile $profile = null,
        public readonly ?FetchMethod $method = null,
        public readonly ?FetchChainDto $chain = null,
        public readonly bool $noFallback = false,
    ) {
    }
}
