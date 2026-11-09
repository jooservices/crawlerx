<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wpb\Types;

use JOOservices\CrawlerX\Adapters\Wpb\Selectors;
use JOOservices\CrawlerX\Adapters\Wpb\Urls;
use JOOservices\CrawlerX\Adapters\Concerns\CoercesValues;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends WpbHtmlType implements TypeInterface
{
    use CoercesValues;

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
            throw new CrawlParseException('WPB listing page did not contain gravure cards.');
        }

        $currentPage = $this->pageNumberFromText($this->firstText($crawler, Selectors::PAGINATION_CURRENT)) ?? $this->urlPage($request->url);
        $pages = $this->pagerPages($crawler, $request->url);
        $nextUrl = $this->nextPageUrl($pages, $currentPage);
        $lastPage = $pages === [] ? null : max(array_keys($pages));

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'gallery',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastPage,
                nextPage: $nextUrl === null ? null : $this->urlPage($nextUrl),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function card(Crawler $card, string $baseUrl): ?CrawlItemResultDto
    {
        $href = $this->firstAttribute($card, Selectors::CARD_LINK, 'href');
        $url = $href === null ? null : Urls::gallery($this->absolute($baseUrl, $href));
        $externalId = $url === null ? null : Urls::externalId($url);
        if ($url === null || $externalId === null) {
            return null;
        }

        $title = $this->firstText($card, Selectors::CARD_TITLE)
            ?? $this->firstAttribute($card, Selectors::CARD_IMAGE, 'alt');
        $published = $this->firstAttribute($card, Selectors::CARD_DATE, 'datetime')
            ?? $this->firstText($card, Selectors::CARD_DATE);
        $cover = $this->cover($card, $baseUrl);

        return GalleryDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'date' => MovieMeta::isoDate($published),
                'photos' => $cover === null ? [] : [$this->photoFromCover($cover)],
                'photo_count' => $cover === null ? 0 : 1,
                'metadata' => MovieMeta::filter([
                    'published_at' => $published,
                    'cover' => $cover,
                    MovieMeta::MAKER => '週刊プレイボーイ',
                ]),
            ],
        )->withNextCrawlType('gallery');
    }

    /** @return array<string, mixed>|null */
    private function cover(Crawler $card, string $baseUrl): ?array
    {
        $pictureRoot = $card->filter(Selectors::CARD_PICTURE);
        $root = $pictureRoot->count() > 0 ? $pictureRoot->first() : $card;
        $picture = $this->picture($root, $baseUrl);
        if ($picture === null) {
            return null;
        }

        return MovieMeta::filter($picture + [
            'mime_type' => Urls::mimeType($picture['url']),
        ]);
    }

    /** @param array<string, mixed> $cover */
    private function photoFromCover(array $cover): PhotoDto
    {
        $url = is_string($cover['url'] ?? null) ? $cover['url'] : '';
        $thumbnail = is_string($cover['thumbnail_url'] ?? null) ? $cover['thumbnail_url'] : null;

        return new PhotoDto(
            url: $url,
            imageUrl: $url,
            thumbnailUrl: $thumbnail,
            position: 1,
            metadata: MovieMeta::filter([
                'alt' => $cover['alt'] ?? null,
                'width' => $cover['width'] ?? null,
                'height' => $cover['height'] ?? null,
                'mime_type' => $url === '' ? null : Urls::mimeType($url),
                'kind' => 'cover',
            ]),
        );
    }

    /**
     * @return array<int, string> page number => absolute URL
     */
    private function pagerPages(Crawler $crawler, string $baseUrl): array
    {
        $pages = [];
        $crawler->filter(Selectors::PAGINATION_LINKS)->each(function (Crawler $link) use (&$pages, $baseUrl): void {
            $href = $link->attr('href');
            $label = $this->normalizeText($link->filter('.navi-pager-a__button__label')->count() > 0
                ? $link->filter('.navi-pager-a__button__label')->text('')
                : $link->text(''));
            if (! is_string($href) || $label === null || ! ctype_digit($label)) {
                return;
            }
            $pages[(int) $label] = $this->absolute($baseUrl, $href);
        });

        $currentLabel = $this->firstText($crawler, Selectors::PAGINATION_CURRENT);
        if ($currentLabel !== null && ctype_digit($currentLabel)) {
            $pages[(int) $currentLabel] ??= $this->pageUrl($baseUrl, (int) $currentLabel);
        }

        ksort($pages);

        return $pages;
    }

    /** @param array<int, string> $pages */
    private function nextPageUrl(array $pages, int $currentPage): ?string
    {
        return $pages[$currentPage + 1] ?? null;
    }

    private function pageUrl(string $baseUrl, int $page): string
    {
        $parts = parse_url($baseUrl);
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : 'wpb.shueisha.co.jp';
        $path = '/gravure/list/';

        return $page <= 1
            ? $scheme . '://' . $host . $path
            : $scheme . '://' . $host . $path . '?page=' . $page;
    }

    private function urlPage(string $url): int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return 1;
        }
        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }
}
