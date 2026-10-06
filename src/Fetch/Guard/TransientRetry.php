<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

use JOOservices\CrawlerX\Dto\FetchResultDto;

final class TransientRetry
{
    public const MAX_RETRIES = 2;

    public const NETWORK_ERROR = 'network_error';

    public const NETWORK_TIMEOUT = 'network_timeout';

    public function isEligible(FetchResultDto $result): bool
    {
        if ($result->ok || $result->challengeDetected || $result->status === 401 || $result->error === 'ssrf_blocked') {
            return false;
        }

        if (in_array($result->status, [502, 503, 504], true)) {
            return ! $this->hasRetryAfter($result);
        }

        return $result->status === 0
            && $result->error === self::NETWORK_ERROR;
    }

    public function canRetry(FetchResultDto $result, int $retriesSoFar): bool
    {
        return $retriesSoFar < self::MAX_RETRIES && $this->isEligible($result);
    }

    public function canAfford(float $remainingSeconds, float $delaySeconds): bool
    {
        return $remainingSeconds >= $delaySeconds + 1.0;
    }

    public function delaySeconds(int $retryNumber): float
    {
        return random_int(500, 1000) / 1000 * (2 ** ($retryNumber - 1));
    }

    private function hasRetryAfter(FetchResultDto $result): bool
    {
        foreach (array_keys($result->headers) as $name) {
            if (strcasecmp($name, 'Retry-After') === 0) {
                return true;
            }
        }

        return false;
    }
}
