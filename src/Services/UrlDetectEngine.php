<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Services;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Dto\UrlDetectionResult;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;
use JOOservices\CrawlerX\Services\Import\ImportUrlNormalizer;

final class UrlDetectEngine
{
    /**
     * @param  list<string>  $extraHostPatterns
     * @param  list<ImportMatchRule>  $rules
     */
    public static function detect(
        string $url,
        string $baseUrl,
        array $extraHostPatterns,
        array $rules,
        ImportUrlNormalizer $normalizer,
    ): UrlDetectionResult {
        $parsed = ImportRuleHelpers::parseUrl($url);
        if ($parsed === null) {
            return UrlDetectionResult::noHostMatch();
        }

        if (! self::hostMatchesUrl($parsed['host'], $baseUrl, $extraHostPatterns)) {
            return UrlDetectionResult::noHostMatch();
        }

        usort($rules, static fn(ImportMatchRule $a, ImportMatchRule $b): int => $b->priority <=> $a->priority);

        foreach ($rules as $rule) {
            if (! $rule->matches($url, $parsed['path'], $parsed['query'])) {
                continue;
            }

            $normalized = self::isDetailCrawlType($rule->crawlType)
                ? $normalizer->normalize($url)['url']
                : null;

            return new UrlDetectionResult(
                hostMatched: true,
                matched: true,
                crawlType: $rule->crawlType,
                entity: $rule->entity,
                urlType: $rule->urlType,
                normalizedUrl: $normalized,
            );
        }

        return UrlDetectionResult::unrecognized();
    }

    /**
     * @param  list<string>  $extraHostPatterns
     */
    private static function hostMatchesUrl(string $host, string $baseUrl, array $extraHostPatterns): bool
    {
        $manifestHost = ImportRuleHelpers::hostFromUrl($baseUrl);
        if ($manifestHost !== null && ImportRuleHelpers::hostMatches($host, $manifestHost)) {
            return true;
        }

        foreach ($extraHostPatterns as $pattern) {
            if (ImportRuleHelpers::hostMatches($host, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function isDetailCrawlType(CrawlType $crawlType): bool
    {
        return $crawlType === CrawlType::Detail
            || $crawlType === CrawlType::Gallery
            || $crawlType === CrawlType::PerformerDetail;
    }
}
