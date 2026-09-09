<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\Catalog;

final class CatalogDefinition
{
    /**
     * @param list<string> $hosts
     * @param list<string> $titleSuffixes
     * @param list<string> $performerSelectors
     * @param list<string> $tagSelectors
     * @param list<string> $screenshotSelectors
     * @param list<string> $detailTitleSelectors
     * @param array<string, string> $coverSelectors
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $baseUrl,
        public readonly array $hosts,
        public readonly string $detailPathPattern,
        public readonly string $listingUrl,
        public readonly string $listingItemSelector,
        public readonly string $listingLinkSelector,
        public readonly array $titleSuffixes = [],
        public readonly array $performerSelectors = [],
        public readonly array $tagSelectors = [],
        public readonly array $screenshotSelectors = [],
        public readonly ?string $externalIdPrefix = null,
        public readonly array $detailTitleSelectors = [],
        public readonly array $coverSelectors = [],
    ) {
    }

    public function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match($this->detailPathPattern, $path, $match) !== 1) {
            return null;
        }

        $id = trim($match[1] ?? '');
        if ($id === '') {
            return null;
        }

        return $this->externalIdPrefix === null ? $id : $this->externalIdPrefix . $id;
    }

    public function isDetailPath(string $path): bool
    {
        return preg_match($this->detailPathPattern, $path) === 1;
    }
}
