<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the site's own `?page=N` pager: the current page, the highest page number shown and the `next` link.
 */
final class ListingPagination
{
    use NormalizesUrls;
    use ParsesHtml;

    public function read(Crawler $dom, CrawlRequestDto $request): CrawlPaginationDto
    {
        [$current, $numbers] = $this->pageNumbers($dom);
        $currentPage = $current ?? max(1, $request->page);
        $nextHref = $this->firstAttribute($dom, '.pagination a.next[href]', 'href');
        $nextUrl = $nextHref === null ? null : $this->absolute(Site::BASE_URL, $nextHref);

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: max([$currentPage, ...$numbers]),
            nextPage: $nextUrl === null ? null : $currentPage + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    /** @return array{0: ?int, 1: list<int>} the current page (if marked) and every numeric page link */
    private function pageNumbers(Crawler $dom): array
    {
        $current = null;
        $numbers = [];
        foreach ($dom->filter('.pagination .page') as $element) {
            $text = trim($element->textContent);
            if (! ctype_digit($text)) {
                continue;
            }

            $numbers[] = (int) $text;
            if (str_contains((string) (new Crawler($element))->attr('class'), 'current')) {
                $current = (int) $text;
            }
        }

        return [$current, $numbers];
    }
}
