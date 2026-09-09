<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use Symfony\Component\DomCrawler\Crawler;

trait ParsesBulmaPagination
{
    /**
     * @param  callable(string, string): string  $normalize
     * @param  (callable(string, int): ?string)|null  $nextUrlFallback
     */
    private function parseBulmaPagination(
        Crawler $crawler,
        string $url,
        callable $normalize,
        ?callable $nextUrlFallback = null,
    ): CrawlPaginationDto {
        $currentNode = $crawler->filter('a.pagination-link.button.is-primary:not(.is-inverted)');

        if ($currentNode->count() === 0) {
            return new CrawlPaginationDto(null, null, null, null, false);
        }

        $currentPage = (int) trim($currentNode->first()->text('0'));
        /** @var array<int, string|null> $pageLinks */
        $pageLinks = [];

        $crawler->filter('a.pagination-link.button.is-primary.is-inverted')->each(
            function (Crawler $node) use (&$pageLinks, $url, $normalize): void {
                $page = (int) trim($node->text('0'));
                $href = $node->attr('href');
                if ($page > 0) {
                    $pageLinks[$page] = is_string($href) && $href !== ''
                        ? $normalize($url, $href)
                        : null;
                }
            },
        );

        $visiblePages = array_keys($pageLinks);

        if ($currentPage < 1 || $visiblePages === []) {
            return new CrawlPaginationDto($currentPage > 0 ? $currentPage : null, null, null, null, false);
        }

        $lastPage = max([$currentPage, ...$visiblePages]);
        $greaterPages = array_values(array_filter($visiblePages, fn(int $p): bool => $p > $currentPage));
        sort($greaterPages);
        $nextPage = $greaterPages[0] ?? null;

        $nextUrl = null;
        if ($nextPage !== null) {
            $nextUrl = $pageLinks[$nextPage] ?? null;
            if ($nextUrl === null && $nextUrlFallback !== null) {
                $nextUrl = $nextUrlFallback($url, $nextPage);
            }
        }

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $lastPage,
            nextPage: $nextPage,
            nextUrl: $nextUrl,
            hasNextPage: $nextPage !== null && $currentPage < $lastPage,
        );
    }
}
