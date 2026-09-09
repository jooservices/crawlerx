<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns\UrlDetect;

use JOOservices\CrawlerX\Dto\ImportMatchRule;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\ImportEntity;
use JOOservices\CrawlerX\Services\Import\ImportRuleHelpers;

trait MissavUrlDetectRules
{
    /** @var list<string> */
    private const RESERVED = [
        'latest-updates',
        'genres',
        'tags',
        'actresses',
        'makers',
        'search',
        'new',
        'popular',
        'release',
        'uncensored-leak',
        'english-subtitle',
    ];

    /** @var list<string> */
    private const LOCALES = ['cn', 'de', 'en', 'fil', 'fr', 'id', 'ja', 'ko', 'ms', 'pt', 'th', 'vi'];

    /**
     * @return list<string>
     */
    protected function urlDetectHostPatterns(): array
    {
        return ['missav.ws', 'missav.ai', 'missav.to', 'missav.com'];
    }

    /**
     * @return list<ImportMatchRule>
     */
    protected function urlDetectRules(): array
    {
        return [
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'import_detail',
                crawlType: CrawlType::Detail,
                priority: 100,
                matcher: fn(string $url, string $path, string $query): bool => ImportRuleHelpers::singleSegmentSlug(
                    $this->missavPathWithoutLocale($path),
                    self::RESERVED,
                ),
            ),
            new ImportMatchRule(
                entity: ImportEntity::Movie,
                urlType: 'listing',
                crawlType: CrawlType::Listing,
                priority: 50,
                matcher: fn(string $url, string $path, string $query): bool => in_array(
                    trim(strtolower($this->missavPathWithoutLocale($path)), '/'),
                    self::RESERVED,
                    true,
                ),
            ),
        ];
    }

    private function missavPathWithoutLocale(string $path): string
    {
        $segments = explode('/', trim(strtolower($path), '/'));
        if (in_array($segments[0], self::LOCALES, true)) {
            array_shift($segments);
        }

        return '/' . implode('/', $segments);
    }
}
