<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

/** Reads the WooCommerce page numbers (`?product-page=N`) the listing prints. */
final class ListingPagination
{
    use NormalizesUrls;
    use ParsesHtml;

    public function fromPage(Crawler $page, CrawlRequestDto $request): CrawlPaginationDto
    {
        $currentPage = $this->currentPage($page, $request);
        $nextHref = $this->firstAttribute($page, Selectors::PAGE_NEXT, 'href');
        $nextUrl = $nextHref === null ? null : $this->absolute(Site::BASE_URL, $nextHref);

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: $this->lastPage($page, $currentPage),
            nextPage: $nextUrl === null ? null : $currentPage + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function currentPage(Crawler $page, CrawlRequestDto $request): int
    {
        $current = $this->firstText($page, Selectors::PAGE_CURRENT);

        return $current !== null && ctype_digit($current) ? (int) $current : max(1, $request->page);
    }

    private function lastPage(Crawler $page, int $currentPage): int
    {
        $numbers = array_filter(
            $page->filter(Selectors::PAGE_NUMBER_LINKS)->each(static fn(Crawler $link): string => trim($link->text(''))),
            static fn(string $text): bool => ctype_digit($text),
        );

        return max([$currentPage, ...array_map('intval', $numbers)]);
    }
}
