<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Indies\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Indies\Fields;
use JOOservices\CrawlerX\Adapters\Indies\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Indies title cards of a month (/ym/YYYYMM/), label (/lables/<slug>/) or genre (/genre/<slug>/) page: title, code,
 * synopsis, release date, package image, price, JAN and (genre pages) the card's genre badges.
 */
final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::LABEL;
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $kind = $this->firstText($crawler, 'section.inner > h1 span.h6');
        $heading = $this->heading($crawler, $kind);
        $items = [];

        $crawler->filter('li.package')->each(function (Crawler $card) use (&$items, $request, $kind, $heading): void {
            $href = $this->firstAttribute($card, 'a[href*="/title/"]', 'href');
            $externalId = $href === null ? null : Fields::slug($href, 'title');
            if ($href === null || $externalId === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $title = $this->firstAttribute($card, 'meta[itemprop="name"]', 'content') ?? $this->firstAttribute($card, 'img', 'alt');
            $cover = $this->firstAttribute($card, 'img[itemprop="image"]', 'src');
            $price = $this->firstAttribute($card, 'meta[itemprop="price"]', 'content');
            $items[$url] ??= MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title ?? $externalId,
                data: MovieMeta::filter([
                    'code' => $this->firstAttribute($card, 'meta[itemprop="sku"]', 'content') ?? $this->firstText($card, 'span.badge-primary'),
                    'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                    'description' => $this->firstAttribute($card, 'meta[itemprop="description"]', 'content'),
                    'date' => MovieMeta::isoDate($this->firstAttribute($card, 'meta[itemprop="releaseDate"]', 'content')),
                    'tags' => $this->texts($card, 'span.badge-secondary'),
                    'metadata' => MovieMeta::filter([
                        MovieMeta::MAKER => Site::MAKER,
                        MovieMeta::PRICE => is_numeric($price)
                            ? MovieMeta::price((float) $price, $this->firstAttribute($card, 'meta[itemprop="priceCurrency"]', 'content'))
                            : null,
                        'jan' => $this->firstAttribute($card, 'meta[itemprop="gtin13"]', 'content'),
                        'listing_kind' => $kind,
                        'listing_heading' => $heading,
                    ]),
                ]),
            );
        });

        if ($items === []) {
            throw new CrawlParseException('Indies listing page did not contain title cards.');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request),
        );
    }

    private function heading(Crawler $crawler, ?string $kind): ?string
    {
        $text = $this->firstText($crawler, 'section.inner > h1 span.flex-grow-1');

        return $text !== null && $kind !== null ? $this->normalizeText(str_replace($kind, '', $text)) : $text;
    }

    /** Label and genre pages page with /page/N/ links; a month page links to the next month ("翌月"). */
    private function pagination(Crawler $crawler, CrawlRequestDto $request): CrawlPaginationDto
    {
        $nextLink = null;
        $lastLink = null;
        $crawler->filter('ul.pagination a.page-link[href]')->each(function (Crawler $link) use (&$nextLink, &$lastLink): void {
            $text = $this->normalizeText($link->text('')) ?? '';
            if (str_starts_with($text, '次')) {
                $nextLink = (string) $link->attr('href');
            } elseif (str_starts_with($text, '>>')) {
                $lastLink = (string) $link->attr('href');
            }
        });

        $nextLink ??= $this->monthLink($crawler);
        $nextUrl = $nextLink === null ? null : $this->absolute($request->url, $nextLink);

        return new CrawlPaginationDto(
            currentPage: $request->page,
            lastPage: $lastLink === null ? null : Fields::pageNumber($lastLink),
            nextPage: $nextUrl === null ? null : (Fields::pageNumber($nextUrl) ?? $request->page + 1),
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function monthLink(Crawler $crawler): ?string
    {
        $link = null;
        $crawler->filter('section.inner > ul a.btn[href]')->each(function (Crawler $anchor) use (&$link): void {
            if ($link === null && $this->normalizeText($anchor->text('')) === '翌月') {
                $link = (string) $anchor->attr('href');
            }
        });

        return $link;
    }
}
