<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class FetchMetaDto extends Dto
{
    /**
     * @param  list<array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}>  $attempts
     */
    public function __construct(
        public readonly string $methodUsed,
        public readonly int $elapsedMs,
        public readonly bool $challengeDetected,
        public readonly array $attempts = [],
    ) {
    }
}
