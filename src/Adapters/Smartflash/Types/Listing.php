<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Smartflash\Types;

use JOOservices\CrawlerX\Adapters\Smartflash\Selectors;
use JOOservices\CrawlerX\Adapters\Smartflash\Urls;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends SmartflashHtmlType implements TypeInterface
{
    protected function contextLabel(): string
    {
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $items = [];

        $crawler->filter(Selectors::LISTING_CARDS)->each(function (Crawler $card) use (&$items, $request): void {
            $item = $this->card($card, $request->url);
            if ($item !== null) {
                $items[$item->url] ??= $item;
            }
        });

        if ($items === []) {
            throw new CrawlParseException('Smartflash listing page did not contain article cards.');
        }

        $currentPage = $this->pageNumber($this->firstText($crawler, Selectors::PAGINATION_CURRENT)) ?? $request->page;
        $nextUrl = $this->linkUrl($crawler, Selectors::PAGINATION_NEXT, $request->url);
        $lastUrl = $this->linkUrl($crawler, Selectors::PAGINATION_LAST, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'gallery',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastUrl === null ? null : $this->urlPage($lastUrl),
                nextPage: $nextUrl === null ? null : $this->urlPage($nextUrl),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function card(Crawler $card, string $baseUrl): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
        $url = $href === null ? null : Urls::article($this->absolute($baseUrl, $href));
        $externalId = $url === null ? null : Urls::externalId($url);
        if ($url === null || $externalId === null) {
            return null;
        }

        $title = $this->firstText($card, Selectors::CARD_TITLE) ?? $this->firstAttribute($card, Selectors::CARD_IMAGE, 'alt');
        $category = $this->firstText($card, Selectors::CARD_CATEGORY);
        $categoryHref = $this->firstAttribute($card, Selectors::CARD_CATEGORY, 'href');
        $source = $card->filter(Selectors::CARD_SOURCE);

        return GalleryDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'date' => MovieMeta::isoDate($this->firstText($card, Selectors::CARD_DATE)),
                'categories' => $category === null ? [] : [$category],
                'tags' => $this->texts($card, Selectors::CARD_TAGS),
                'metadata' => MovieMeta::filter([
                    'category_url' => $categoryHref === null ? null : $this->absolute($baseUrl, $categoryHref),
                    'tag_urls' => $this->hrefs($card, Selectors::CARD_TAGS, $baseUrl),
                    'source' => $source->count() === 0 ? null : [
                        'name' => $this->normalizeText($source->first()->text('')),
                        'url' => $this->absolute($baseUrl, (string) $source->first()->attr('href')),
                    ],
                    'cover' => $this->cover($card, $baseUrl),
                ]),
            ],
        )->withNextCrawlType('gallery');
    }

    /** @return array<string, mixed>|null */
    private function cover(Crawler $card, string $baseUrl): ?array
    {
        $src = $this->firstAttribute($card, Selectors::CARD_IMAGE, 'data-src')
            ?? $this->firstAttribute($card, Selectors::CARD_IMAGE, 'src');
        if ($src === null) {
            return null;
        }

        $thumbnail = $this->absolute($baseUrl, $src);
        $original = Urls::original($thumbnail);

        return MovieMeta::filter([
            'url' => $original,
            'thumbnail_url' => $thumbnail,
            'mime_type' => Urls::mimeType($original),
        ]);
    }

    private function linkUrl(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, $selector, 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }

    private function pageNumber(?string $text): ?int
    {
        return $text !== null && ctype_digit($text) ? max(1, (int) $text) : null;
    }

    private function urlPage(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/(\d+)/?$#', $path, $match) === 1 ? (int) $match[1] : null;
    }
}
