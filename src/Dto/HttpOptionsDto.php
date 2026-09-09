<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class HttpOptionsDto extends Dto
{
    /**
     * @param  array<string, string>|null  $headers
     */
    public function __construct(
        public readonly ?int $timeout = null,
        public readonly ?bool $verifySsl = null,
        public readonly ?array $headers = null,
    ) {
    }
}
