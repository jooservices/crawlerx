<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravurech\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gravurech\ContentUrl;
use JOOservices\CrawlerX\Adapters\Gravurech\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'gravurech';
    }

    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
            $code = $href === null ? null : ContentUrl::code($href);
            if ($href === null || $code === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $image = $this->firstAttribute($card, Selectors::CARD_IMAGE, 'src');
            $thumbnail = $image === null ? null : $this->absolute($request->url, $image);
            $category = $this->firstText($card, Selectors::CARD_CATEGORY);

            $items[$url] = GalleryDto::item(
                url: $url,
                externalId: $code,
                title: $this->firstText($card, Selectors::CARD_TITLE) ?? $code,
                data: [
                    'categories' => $category === null ? [] : [$category],
                    'metadata' => [
                        'content_id' => ContentUrl::numericId($thumbnail),
                        'thumbnail_url' => $thumbnail,
                    ],
                ],
            )->withNextCrawlType('gallery');
        });

        if ($items === []) {
            throw new CrawlParseException('Gravurech listing page did not contain work cards.');
        }

        $current = $this->currentPage($crawler, $request);
        $nextHref = $this->firstAttribute($crawler, Selectors::PAGINATION_NEXT, 'href');
        $nextUrl = $nextHref === null ? null : $this->absolute($request->url, $nextHref);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'gallery',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $current,
                lastPage: $this->lastPage($crawler, $current),
                nextPage: $nextUrl === null ? null : ContentUrl::page($nextUrl),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function currentPage(Crawler $crawler, CrawlRequestDto $request): int
    {
        $text = $this->firstText($crawler, Selectors::PAGINATION_CURRENT);

        return $text !== null && ctype_digit($text) ? (int) $text : ContentUrl::page($request->url);
    }

    /** The site has no "last" link on the final page, so the highest numbered page link is used. */
    private function lastPage(Crawler $crawler, int $current): int
    {
        $numbers = $crawler->filter(Selectors::PAGINATION_PAGES)->each(
            fn(Crawler $node): int => ctype_digit((string) $this->normalizeText($node->text(''))) ? (int) $node->text('') : 0,
        );

        return max([$current, ...$numbers]);
    }
}
