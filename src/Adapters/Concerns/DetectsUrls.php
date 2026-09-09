<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;
use JOOservices\CrawlerX\Services\Import\ImportUrlNormalizer;
use JOOservices\CrawlerX\Services\UrlDetectEngine;

/**
 * @phpstan-require-implements UrlDetectCapable
 */
trait DetectsUrls
{
    /**
     * @return list<string>
     */
    abstract protected function urlDetectHostPatterns(): array;

    /**
     * @return list<\JOOservices\CrawlerX\Dto\ImportMatchRule>
     */
    abstract protected function urlDetectRules(): array;

    public function detectUrl(string $url): UrlDetectionResult
    {
        $manifest = $this->manifest();

        return UrlDetectEngine::detect(
            url: $url,
            baseUrl: $manifest->baseUrl,
            extraHostPatterns: $this->urlDetectHostPatterns(),
            rules: $this->urlDetectRules(),
            normalizer: new ImportUrlNormalizer(),
        );
    }
}
