<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Deeps\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Deeps\Selectors;
use JOOservices\CrawlerX\Adapters\Deeps\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'DEEP\'S';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $total = $this->total($crawler);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request, $total): void {
            $href = $this->firstAttribute($card, 'a[href]', 'href');
            $externalId = $href === null ? null : Site::externalId($href);
            if ($href === null || $externalId === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $cover = $this->firstAttribute($card, 'img[src]', 'src');
            $items[$url] ??= MovieDto::item($url, $externalId, null, MovieMeta::filter([
                'code' => strtoupper($externalId),
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'description' => $this->firstText($card, '.caption'),
                'metadata' => MovieMeta::filter([
                    'release_class' => $this->normalizeText((string) $card->attr('class')),
                    'listing_total' => $total,
                ]),
            ]));
        });

        if ($items === []) {
            throw new CrawlParseException('DEEP\'S listing page did not contain movie cards.');
        }

        $page = Site::pageNumber($request->url) ?? $request->page;

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request->url, $page),
        );
    }

    /** The page header says "全 2662 タイトル": the size of the whole catalogue. */
    private function total(Crawler $crawler): ?int
    {
        $text = $this->firstText($crawler, Selectors::LISTING_TOTAL);

        return $text !== null && preg_match('/(\d+)/', $text, $match) === 1 ? (int) $match[1] : null;
    }

    private function pagination(Crawler $crawler, string $baseUrl, int $page): CrawlPaginationDto
    {
        $links = [];
        $crawler->filter(Selectors::PAGE_LINKS)->each(function (Crawler $link) use (&$links, $baseUrl): void {
            $href = $link->attr('href');
            $number = is_string($href) ? Site::pageNumber($href) : null;
            if ($number !== null) {
                $links[$number] = $this->absolute($baseUrl, $href);
            }
        });

        $nextUrl = $links[$page + 1] ?? null;

        return new CrawlPaginationDto(
            currentPage: $page,
            lastPage: $links === [] ? null : max(array_keys($links)),
            nextPage: $nextUrl === null ? null : $page + 1,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }
}
