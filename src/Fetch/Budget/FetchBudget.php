<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Budget;

use Closure;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Enums\FetchMethod;

final class FetchBudget
{
    public const DEFAULT_TOTAL_SECONDS = 150;

    public const HTTP_CAP_SECONDS = 20;

    public const BROWSER_CAP_SECONDS = 45;

    public const FLARE_CAP_SECONDS = 60;

    /** @param Closure(): (int|float) $clock */
    public function __construct(
        ?int $deadlineSeconds = null,
        int|float|null $startedAt = null,
        ?Closure $clock = null,
    ) {
        $this->deadline = max(1, min(self::DEFAULT_TOTAL_SECONDS, $deadlineSeconds ?? self::DEFAULT_TOTAL_SECONDS));
        $this->startedAt = $startedAt ?? hrtime(true);
        $this->clock = $clock;
    }

    private readonly int|float $startedAt;

    /** @var Closure(): (int|float)|null */
    private readonly ?Closure $clock;

    private readonly int $deadline;

    public static function start(?FetchOptionsDto $options): self
    {
        return new self($options?->deadlineSeconds, hrtime(true));
    }

    public function elapsedSeconds(): float
    {
        $now = $this->clock !== null ? ($this->clock)() : hrtime(true);

        return max(0.0, ($now - $this->startedAt) / 1_000_000_000);
    }

    public function remainingSeconds(): float
    {
        return max(0.0, $this->deadline - $this->elapsedSeconds());
    }

    public function methodCap(FetchMethod $method): int
    {
        return match ($method) {
            FetchMethod::Http, FetchMethod::CurlImpersonate => self::HTTP_CAP_SECONDS,
            FetchMethod::Flaresolverr => self::FLARE_CAP_SECONDS,
            default => self::BROWSER_CAP_SECONDS,
        };
    }

    public function timeoutSeconds(FetchMethod $method): int
    {
        return max(1, (int) min($this->methodCap($method), floor($this->remainingSeconds())));
    }

    public function canStart(FetchMethod $method): bool
    {
        return $this->remainingSeconds() >= 1.0 && $this->remainingSeconds() >= min(1, $this->methodCap($method));
    }

    public function deadlineSeconds(): int
    {
        return $this->deadline;
    }
}
