<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\PerformerDirectory;

final class PerformerDirectoryDefinition
{
    /**
     * @param list<string> $hosts
     * @param list<string> $titleSelectors
     * @param list<string> $imageSelectors
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $listingUrl,
        public readonly array $hosts,
        public readonly string $listingLinkSelector,
        public readonly string $detailUrlPattern,
        public readonly array $titleSelectors,
        public readonly array $imageSelectors,
    ) {
    }

    public function externalId(string $url): ?string
    {
        if (preg_match($this->detailUrlPattern, $url, $matches) !== 1) {
            return null;
        }

        $id = trim($matches[1] ?? '');

        return $id === '' ? null : $id;
    }
}
