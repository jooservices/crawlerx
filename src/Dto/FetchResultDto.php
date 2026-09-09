<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\Dto\Core\Dto;

final class FetchResultDto extends Dto
{
    /**
     * @param  list<array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}>  $attempts
     * @param  array<string, string>  $cookies
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $body,
        public readonly int $status,
        public readonly FetchMethod $methodUsed,
        public readonly int $elapsedMs,
        public readonly bool $challengeDetected,
        public readonly ?string $finalUrl = null,
        public readonly array $attempts = [],
        public readonly array $cookies = [],
        public readonly array $headers = [],
        public readonly ?string $error = null,
    ) {
    }

    public function toMeta(): FetchMetaDto
    {
        return new FetchMetaDto(
            methodUsed: $this->methodUsed->value,
            elapsedMs: $this->elapsedMs,
            challengeDetected: $this->challengeDetected,
            attempts: $this->attempts,
        );
    }

    /**
     * @param  list<array{method: string, elapsed_ms: int, status: int, challenge: bool, ok: bool, error?: string|null}>  $attempts
     */
    public function withAttempts(array $attempts): self
    {
        return new self(
            ok: $this->ok,
            body: $this->body,
            status: $this->status,
            methodUsed: $this->methodUsed,
            elapsedMs: $this->elapsedMs,
            challengeDetected: $this->challengeDetected,
            finalUrl: $this->finalUrl,
            attempts: $attempts,
            cookies: $this->cookies,
            headers: $this->headers,
            error: $this->error,
        );
    }
}
