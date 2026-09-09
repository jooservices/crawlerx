<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;

final class FetchPlanResolver
{
    /**
     * @return list<FetchMethod>
     */
    public function resolve(SiteProfileDto $profile, ?FetchOptionsDto $options = null): array
    {
        if (ClientBuilder::isFaked()) {
            return FetchMethod::httpOnlyChain();
        }

        if ($options?->chain instanceof \JOOservices\CrawlerX\Dto\FetchChainDto && $options->chain->methods !== []) {
            return $options->chain->methods;
        }

        $base = $options?->profile instanceof \JOOservices\CrawlerX\Enums\FetchProfile
            ? $options->profile->chain()
            : $profile->fetchChain;

        if ($options?->method instanceof FetchMethod) {
            if ($options->noFallback) {
                return [$options->method];
            }

            return $this->startAt($base, $options->method);
        }

        return $base;
    }

    /**
     * @param  list<FetchMethod>  $base
     * @return list<FetchMethod>
     */
    private function startAt(array $base, FetchMethod $method): array
    {
        $started = false;
        $plan = [];

        foreach ($base as $entry) {
            if ($entry === $method) {
                $started = true;
            }

            if ($started) {
                $plan[] = $entry;
            }
        }

        if ($plan === []) {
            return [$method, ...$base];
        }

        return $plan;
    }
}
