<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Dandy\Fields;
use JOOservices\CrawlerX\Adapters\Dandy\Selectors;
use JOOservices\CrawlerX\Adapters\Dandy\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Work cards of the home page, a label page (`/dandy/`), the full catalog (`/dandy/works/`, paginated) and a genre
 * page (`/dandy/genre/<name>/`); all of them share the same `works_list` markup.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    protected function siteLabel(): string
    {
        return 'DANDY GROUP';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $dom = $this->fetchCrawler($request);
        $heading = Fields::text($dom, Selectors::LIST_HEADING);
        $total = $this->total(Fields::text($dom, Selectors::LIST_COUNT));
        $items = [];
        foreach ($dom->filter(Selectors::CARDS) as $element) {
            $item = $this->card(new Crawler($element), $heading, $total);
            if ($item !== null) {
                $items[$item->url] = $item;
            }
        }
        if ($items === []) {
            throw new CrawlParseException('DANDY GROUP listing did not contain expected work cards.');
        }

        $pagination = $this->pagination($dom, $request);

        return new CrawlListResultDto(
            url: $request->url,
            page: $pagination->currentPage ?? 1,
            entityType: 'movie',
            items: array_values($items),
            pagination: $pagination,
        );
    }

    private function card(Crawler $card, ?string $heading, ?int $total): ?CrawlItemResultDto
    {
        $link = $card->filter(Selectors::CARD_LINK);
        $url = $link->count() > 0 ? Site::absolute((string) $link->first()->attr('href')) : '';
        $slug = Site::workSlug($url);
        if ($slug === null) {
            return null;
        }

        $dates = $this->dates($card);
        $image = $card->filter(Selectors::CARD_IMAGE)->count() > 0
            ? Fields::image($card->filter(Selectors::CARD_IMAGE)->first())
            : null;

        return MovieDto::item($url, $slug, Fields::text($card, Selectors::CARD_TITLE), MovieMeta::filter([
            'code' => strtoupper($slug),
            'cover_url' => $image['url'] ?? null,
            'date' => $dates['stream_date'] ?? $dates['dvd_release_date'] ?? null,
            'label_slug' => Site::labelSlug($url),
            'stream_date' => $dates['stream_date'] ?? null,
            'dvd_release_date' => $dates['dvd_release_date'] ?? null,
            'cover_thumbnail_url' => $image['thumbnail_url'] ?? null,
            'listing_title' => $heading,
            'total_results' => $total,
        ]));
    }

    /**
     * Cards print `動画配信日：2026/11/29` (stream date) and, for physical releases, `DVD発売日：2026/12/03`.
     *
     * @return array<string, string>
     */
    private function dates(Crawler $card): array
    {
        $dates = [];
        foreach ($card->filter(Selectors::CARD_DATES) as $element) {
            $text = Fields::clean((new Crawler($element))->text('')) ?? '';
            $date = Fields::isoDate($text);
            if ($date === null) {
                continue;
            }
            $dates[str_starts_with($text, 'DVD') ? 'dvd_release_date' : 'stream_date'] = $date;
        }

        return $dates;
    }

    private function total(?string $text): ?int
    {
        return $text !== null && preg_match('/(\d[\d,]*)\s*件/u', $text, $match) === 1
            ? (int) str_replace(',', '', $match[1])
            : null;
    }

    private function pagination(Crawler $dom, CrawlRequestDto $request): CrawlPaginationDto
    {
        $current = Fields::text($dom, Selectors::PAGINATION_CURRENT);
        $page = $current !== null && ctype_digit($current) ? (int) $current : max(1, $request->page);
        $next = $dom->filter(Selectors::PAGINATION_NEXT);
        $nextUrl = $next->count() > 0 ? Site::absolute((string) $next->first()->attr('href')) : null;

        return new CrawlPaginationDto(
            currentPage: $page,
            lastPage: $this->lastPage($dom, $page),
            nextPage: $nextUrl !== null ? $page + 1 : null,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function lastPage(Crawler $dom, int $page): int
    {
        $last = $dom->filter(Selectors::PAGINATION_LAST);
        if ($last->count() > 0 && preg_match('#/page/(\d+)/?#', (string) $last->first()->attr('href'), $match) === 1) {
            return (int) $match[1];
        }

        $numbers = array_map('intval', array_filter(
            $dom->filter(Selectors::PAGINATION_PAGES)->each(static fn(Crawler $node): string => trim($node->text(''))),
            'ctype_digit',
        ));

        return max([$page, ...$numbers]);
    }
}
