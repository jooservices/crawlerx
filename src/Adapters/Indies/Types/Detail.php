<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Indies\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Indies\Fields;
use JOOservices\CrawlerX\Adapters\Indies\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Indies title page: the labelled information rows (performers, code, series/label, director, list price, runtime,
 * release date, keywords, JAN, review number), synopsis, package image, gallery, sample video and purchase links.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::LABEL;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        $texts = array_map(static fn(Crawler $cell): ?string => Fields::visibleText($cell), $rows);
        $externalId = Fields::slug($request->url, 'title');
        $genres = $this->links($rows['キーワード'] ?? null, $request->url);
        $label = $this->links($rows['シリーズ'] ?? null, $request->url)[0] ?? null;
        $director = $this->director($texts['監督'] ?? null);
        $prices = Fields::prices($texts['定価'] ?? null);
        $date = $this->firstAttribute($crawler, 'meta[itemprop="releaseDate"]', 'content') ?? $texts['発売日'] ?? null;

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, 'h1[itemprop="name"]') ?? $externalId,
            data: MovieMeta::filter([
                'code' => $texts['品番'] ?? null,
                'cover_url' => $this->link($crawler, 'a.img_popup', $request->url),
                'description' => $this->firstText($crawler, 'div.ttl div.col-md-12 span.h6'),
                'date' => MovieMeta::isoDate($date),
                'duration' => MovieMeta::durationMinutes($texts['収録時間'] ?? null),
                'performers' => $this->performers($texts['女優名'] ?? null),
                'tags' => array_column($genres, 'name'),
                'screenshots' => $this->gallery($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::MAKER,
                    MovieMeta::SERIES => $texts['シリーズ'] ?? null,
                    MovieMeta::LABEL => $label['name'] ?? null,
                    'label_url' => $label['url'] ?? null,
                    MovieMeta::DIRECTOR => $director['name'] ?? null,
                    'director_role' => $director['role'] ?? null,
                    MovieMeta::PRICE => $prices['price'],
                    'price_with_tax' => $prices['price_with_tax'],
                    'jan' => $texts['JAN'] ?? null,
                    'review_number' => $texts['審査番号'] ?? null,
                    MovieMeta::RELEASE_DATE_RAW => $texts['発売日'] ?? null,
                    'genres' => $genres,
                    'cover_thumbnail_url' => $this->imageSource($crawler, 'a.img_popup img', $request->url),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->imageSource($crawler, 'video source', $request->url),
                    'sample_video_poster_url' => $this->poster($crawler, $request->url),
                    'purchase_links' => $this->purchaseLinks($crawler, $request->url),
                    'source_fields' => $texts,
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, Site::LABEL);

        return $item;
    }

    /** @return array<string, Crawler> row label => value cell; the page prints every row twice (mobile and desktop) */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('div.ttl li.d-flex')->each(function (Crawler $row) use (&$rows): void {
            $cells = $row->children();
            $label = $cells->count() > 1 ? $this->normalizeText($cells->eq(0)->text('')) : null;
            if ($label !== null) {
                $rows[$label] ??= $cells->eq(1);
            }
        });

        return $rows;
    }

    /** @return list<string> the "A/B/C" cast text as one performer each */
    private function performers(?string $text): array
    {
        if ($text === null) {
            return [];
        }

        $names = preg_split('~[/／]~u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $names === false ? [] : array_values(array_filter(
            array_map('trim', $names),
            static fn(string $name): bool => $name !== '',
        ));
    }

    /** @return array{role: ?string, name: string}|null "監修：Marchin" is the role "監修" and the name "Marchin" */
    private function director(?string $text): ?array
    {
        if ($text === null || preg_match('/^[-ー－\x{2010}-\x{2015}]+$/u', $text) === 1) {
            return null;
        }

        return preg_match('/^(監修|監督)\s*[：:]\s*(.+)$/u', $text, $match) === 1
            ? ['role' => $match[1], 'name' => $match[2]]
            : ['role' => null, 'name' => $text];
    }

    /** @return list<array{name: string, url: string}> */
    private function links(?Crawler $cell, string $baseUrl): array
    {
        $links = [];
        $cell?->filter('a[href]')->each(function (Crawler $link) use (&$links, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            if ($name !== null) {
                $links[] = ['name' => $name, 'url' => $this->absolute($baseUrl, (string) $link->attr('href'))];
            }
        });

        return $links;
    }

    private function link(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, $selector, 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }

    private function imageSource(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, $selector, 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    private function poster(Crawler $crawler, string $baseUrl): ?string
    {
        $poster = $this->firstAttribute($crawler, 'video', 'poster');

        return $poster === null ? null : $this->absolute($baseUrl, $poster);
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> the gallery, without its empty slots */
    private function gallery(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter('#gallery a[href]')->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = trim((string) $link->attr('href'));
            if ($href === '' || isset($images[$href])) {
                return;
            }

            $thumbnail = $this->firstAttribute($link, 'img', 'src');
            $images[$href] = [
                'url' => $this->absolute($baseUrl, $href),
                'thumbnail_url' => $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                'position' => count($images),
            ];
        });

        return array_values($images);
    }

    /** @return list<array{name: string, url: string}> the DMM / FANZA / DUGA buttons under the information table */
    private function purchaseLinks(Crawler $crawler, string $baseUrl): array
    {
        $links = [];
        $crawler->filter('a.btn.btn-outline-primary[target="_blank"][href]')->each(function (Crawler $link) use (&$links, $baseUrl): void {
            $url = $this->absolute($baseUrl, trim((string) $link->attr('href')));
            $name = $this->normalizeText($link->text('')) ?? $this->firstAttribute($link, 'img', 'alt');
            if ($name !== null && ! isset($links[$url])) {
                $links[$url] = ['name' => $name, 'url' => $url];
            }
        });

        return array_values($links);
    }
}
