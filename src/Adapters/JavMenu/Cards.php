<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parsing shared by every page built from the site's card grid and pager: work listings, the performer index and
 * the performer page's own work grid.
 */
final class Cards
{
    use NormalizesUrls;
    use ParsesHtml;

    /**
     * The work cards of a page, keyed by work URL. Advertising cards link to other sites and are skipped.
     *
     * @return array<string, array{url: string, external_id: string, code: string, title: ?string, date: ?string, cover_url: ?string, online_available: bool, downloadable: bool, is_new: bool}>
     */
    public function works(Crawler $crawler, string $baseUrl): array
    {
        $works = [];
        $crawler->filter(Selectors::CARD)->each(function (Crawler $card) use (&$works, $baseUrl): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $url = $href === null ? null : $this->absolute($baseUrl, $href);
            $externalId = $url === null ? null : Site::workId($url);
            if ($url === null || $externalId === null || isset($works[$url])) {
                return;
            }

            $works[$url] = [
                'url' => $url,
                'external_id' => $externalId,
                'code' => $externalId,
                'title' => $this->title($card, $externalId),
                'date' => MovieMeta::isoDate($this->firstText($card, Selectors::CARD_DATE)),
                'cover_url' => $this->cover($card, $baseUrl),
                'online_available' => $card->filter(Selectors::BADGE_ONLINE)->count() > 0,
                'downloadable' => $card->filter(Selectors::BADGE_DOWNLOAD)->count() > 0,
                'is_new' => $card->filter(Selectors::BADGE_NEW)->count() > 0,
            ];
        });

        return $works;
    }

    /** The lazy-loaded image of a card; the site's loading and placeholder images are not data. */
    public function cover(Crawler $card, string $baseUrl): ?string
    {
        foreach (['data-src', 'src'] as $attribute) {
            $value = $this->firstAttribute($card, Selectors::CARD_COVER, $attribute);
            if ($value !== null && ! str_contains($value, '/theme/')) {
                return $this->absolute($baseUrl, $value);
            }
        }

        return null;
    }

    /** The pager: current page, next page (the icon-only link ahead of the current page) and the highest page shown. */
    public function pagination(Crawler $crawler, string $requestUrl, int $requestedPage): CrawlPaginationDto
    {
        $current = (int) $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        $current = $current > 0 ? $current : max(1, Site::pageNumber($requestUrl) ?? $requestedPage);
        $last = $current;
        $nextUrl = null;
        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $link) use (&$last, &$nextUrl, $current, $requestUrl): void {
            $url = $this->absolute($requestUrl, (string) $link->attr('href'));
            $page = Site::pageNumber($url) ?? 0;
            $last = max($last, $page);
            if ($page > $current && $this->normalizeText($link->text('')) === null) {
                $nextUrl = $url;
            }
        });

        return new CrawlPaginationDto(
            currentPage: $current,
            lastPage: $last,
            nextPage: $nextUrl === null ? null : $current + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    /** The card title is "CODE title"; the code is a field of its own (the work id is the code). */
    private function title(Crawler $card, string $code): ?string
    {
        $title = $this->firstAttribute($card, Selectors::CARD_TITLE, 'title') ?? $this->firstText($card, Selectors::CARD_TITLE);
        if ($title === null || ! str_starts_with($title, $code . ' ')) {
            return $title;
        }

        return trim(substr($title, strlen($code)));
    }
}
