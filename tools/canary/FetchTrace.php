<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;

final class FetchTrace
{
    /** @var list<array<string, mixed>> */
    public array $attempts = [];

    public function reset(): void
    {
        $this->attempts = [];
    }

    public function handler(FetchMethodHandler $inner): FetchMethodHandler
    {
        return new TracingFetchMethodHandler($inner, $this);
    }
}

final class TracingFetchMethodHandler implements FetchMethodHandler
{
    public function __construct(
        private readonly FetchMethodHandler $inner,
        private readonly FetchTrace $trace,
    ) {
    }

    public function supports(FetchMethod $method): bool
    {
        return $this->inner->supports($method);
    }

    public function fetch(
        string $url,
        SiteProfileDto $profile,
        FetchMethod $method,
        ?CrawlOptionsDto $options = null,
    ): FetchResultDto {
        try {
            $result = $this->inner->fetch($url, $profile, $method, $options);
        } catch (\Throwable $exception) {
            $this->trace->attempts[] = [
                'method' => $method->value,
                'elapsed_ms' => 0,
                'status' => 0,
                'bytes' => 0,
                'challenge' => false,
                'ok' => false,
                'error' => Redactor::text($exception->getMessage(), []),
            ];
            throw $exception;
        }

        $this->trace->attempts[] = [
            'method' => $method->value,
            'elapsed_ms' => $result->elapsedMs,
            'status' => $result->status,
            'bytes' => strlen($result->body),
            'challenge' => $result->challengeDetected,
            'ok' => $result->ok,
            'error' => $result->error,
        ];

        return $result;
    }
}
