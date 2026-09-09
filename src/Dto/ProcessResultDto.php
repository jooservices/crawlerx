<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class ProcessResultDto extends Dto
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr = '',
    ) {
    }
}
