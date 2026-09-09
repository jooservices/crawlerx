<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class HttpProfileDto extends Dto
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $timeout = 30,
        public readonly bool $verifySsl = true,
        public readonly array $headers = [],
    ) {
    }
}
