<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the shop's own pager (`ul.pager`): the current page, every numbered `?page=N` link and the next page link.
 */
final class Pagination
{
    use NormalizesUrls;
    use ParsesHtml;

    public function read(Crawler $crawler, string $requestUrl, int $requestPage): CrawlPaginationDto
    {
        $currentText = $this->firstText($crawler, Selectors::PAGER_CURRENT);
        $current = $currentText !== null && ctype_digit($currentText) ? (int) $currentText : max(1, $requestPage);
        $numbers = [$current];
        $nextUrl = null;

        $crawler->filter(Selectors::PAGER_LINKS)->each(function (Crawler $link) use (&$numbers, &$nextUrl, $current, $requestUrl): void {
            $page = $this->pageNumber($link->attr('href'));
            if ($page === null) {
                return;
            }

            $numbers[] = $page;
            if ($page === $current + 1) {
                $nextUrl = $this->absolute($requestUrl, (string) $link->attr('href'));
            }
        });

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: max($numbers),
            nextPage: $nextUrl === null ? null : $current + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function pageNumber(?string $href): ?int
    {
        return $href !== null && preg_match('/[?&]page=(\d+)/', $href, $match) === 1 ? (int) $match[1] : null;
    }
}
