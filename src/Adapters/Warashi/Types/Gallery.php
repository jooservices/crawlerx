<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Warashi\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Warashi\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'warashi';
    }

    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, Selectors::GALLERY_TITLE) ?? $externalId;
        $photos = $this->photos($crawler, $request->url);

        if ($title === null || $photos === []) {
            throw new CrawlParseException('Warashi gallery page did not contain expected gallery fields.');
        }

        $item = GalleryDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'photo_count' => count($photos),
                'performers' => $this->performers($crawler),
                'photos' => $photos,
                'metadata' => [
                    'source' => $this->firstText($crawler, Selectors::GALLERY_SOURCE_LINK),
                    'performer_url' => $this->firstAttribute($crawler, Selectors::GALLERY_PERFORMER_LINK, 'href'),
                    'source_url' => $this->firstAttribute($crawler, Selectors::GALLERY_SOURCE_LINK, 'href'),
                    'related_content' => $this->relatedContent($crawler, $request->url),
                ],
            ],
        );

        return $item;
    }

    /** @return array{title: ?string, original_title: ?string, code: ?string, date: ?string, studio: ?string, url: ?string, cover_url: ?string}|null the web scene the pictures belong to */
    private function relatedContent(Crawler $crawler, string $baseUrl): ?array
    {
        $box = $crawler->filter('#fiche-galerie-listing-photos-partenaires .contenu_lie');
        if ($box->count() === 0) {
            return null;
        }

        $paragraphs = $box->first()->filter('p')->each(fn(Crawler $p): ?string => $this->normalizeText($p->text('')));
        $link = $box->first()->filter('a[href]');
        $image = $box->first()->filter('img');

        return [
            'title' => $paragraphs[0] ?? null,
            'original_title' => $paragraphs[1] ?? null,
            'code' => $paragraphs[2] ?? null,
            'date' => MovieMeta::isoDate($paragraphs[3] ?? null) ?? $this->englishDate($paragraphs[3] ?? null),
            'studio' => $paragraphs[4] ?? null,
            'url' => $link->count() > 0 ? $this->absolute($baseUrl, (string) $link->first()->attr('href')) : null,
            'cover_url' => $image->count() > 0 ? $this->absolute($baseUrl, (string) $image->first()->attr('src')) : null,
        ];
    }

    /** "July 07, 2023" */
    private function englishDate(?string $text): ?string
    {
        $date = $text === null ? false : date_create_immutable($text);

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * @return list<PhotoDto>
     */
    private function photos(Crawler $crawler, string $baseUrl): array
    {
        if ($crawler->filter(Selectors::GALLERY_PHOTOS)->count() === 0) {
            return [];
        }

        $photos = [];
        $crawler->filter(Selectors::GALLERY_PHOTOS)->each(function (Crawler $node) use (&$photos, $baseUrl): void {
            $large = $node->attr('href');
            $thumbnail = $node->filter('img')->count() > 0
                ? $node->filter('img')->first()->attr('src')
                : null;

            $photos[] = new PhotoDto(
                url: $large === null ? null : $this->absolute($baseUrl, $large),
                imageUrl: $large === null ? null : $this->absolute($baseUrl, $large),
                thumbnailUrl: $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                position: count($photos) + 1,
            );
        });

        return $photos;
    }

    /**
     * @return list<string>
     */
    private function performers(Crawler $crawler): array
    {
        return array_values(array_filter(
            $crawler->filter(Selectors::GALLERY_PERFORMER_LINK)->each(fn(Crawler $node): string => $this->normalizeText($node->text('')) ?? ''),
            fn(string $label): bool => $label !== '',
        ));
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/photo-gallery/(\d+)/?$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
