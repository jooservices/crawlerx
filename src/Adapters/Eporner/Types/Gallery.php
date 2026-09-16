<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Eporner\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Eporner\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'eporner';
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
            throw new CrawlParseException('Eporner gallery page did not contain expected gallery fields.');
        }

        $item = GalleryDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'photo_count' => $this->photoCount($crawler) ?? count($photos),
                'views' => $this->views($crawler),
                'rating' => $this->firstText($crawler, Selectors::GALLERY_RATING),
                'votes' => $this->votes($crawler),
                'uploader' => $this->firstText($crawler, Selectors::GALLERY_UPLOADER),
                'uploader_url' => $this->firstAttribute($crawler, Selectors::GALLERY_UPLOADER, 'href'),
                'date' => $this->firstAttribute($crawler, Selectors::GALLERY_DATE, 'datetime'),
                'performers' => $this->linkLabels($crawler, Selectors::GALLERY_PORNSTARS),
                'categories' => $this->linkLabels($crawler, Selectors::GALLERY_CATEGORIES),
                'tags' => $this->linkLabels($crawler, Selectors::GALLERY_TAGS),
                'photos' => $photos,
            ],
        );

        return $item;
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
            $thumbnail = $this->firstAttribute($node, Selectors::PHOTO_IMAGE, 'src');
            $link = $this->firstAttribute($node, Selectors::PHOTO_LINK, 'href');
            $photos[] = new PhotoDto(
                id: $node->attr('data-gallery-photo'),
                url: $link === null ? null : $this->absolute($baseUrl, $link),
                imageUrl: $this->fullImageUrl($baseUrl, $thumbnail),
                thumbnailUrl: $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                position: $this->photoPosition($node),
                views: $this->photoViews($node),
                rating: $this->firstText($node, Selectors::PHOTO_RATING),
            );
        });

        return $photos;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/gallery/([^/]+)/#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function photoCount(Crawler $crawler): ?int
    {
        return $this->metaStrong($crawler, 0);
    }

    private function views(Crawler $crawler): ?int
    {
        return $this->metaStrong($crawler, 1);
    }

    private function metaStrong(Crawler $crawler, int $index): ?int
    {
        $node = $crawler->filter(Selectors::GALLERY_META_STRONGS);
        if ($node->count() <= $index) {
            return null;
        }

        return $this->toInt($node->eq($index)->text(''));
    }

    private function votes(Crawler $crawler): ?int
    {
        $text = $this->firstText($crawler, Selectors::GALLERY_VOTES);

        return $text === null ? null : $this->toInt($text);
    }

    private function photoPosition(Crawler $node): ?int
    {
        $title = $node->filter(Selectors::PHOTO_NUMBER)->count() > 0
            ? $node->filter(Selectors::PHOTO_NUMBER)->first()->attr('title')
            : null;

        return is_string($title) ? $this->firstInt($title) : null;
    }

    private function photoViews(Crawler $node): ?int
    {
        $text = $this->firstText($node, Selectors::PHOTO_VIEWS);

        return $text === null ? null : $this->toInt($text);
    }

    private function fullImageUrl(string $baseUrl, ?string $thumbnail): ?string
    {
        if ($thumbnail === null) {
            return null;
        }

        $full = preg_replace('/_\d+x\d+(?=\.(?:jpe?g|png|webp|gif)$)/i', '', $thumbnail);

        return is_string($full) && trim($full) !== '' ? $this->absolute($baseUrl, trim($full)) : null;
    }

    /**
     * @return list<string>
     */
    private function linkLabels(Crawler $crawler, string $selector): array
    {
        return array_values(array_filter(
            $crawler->filter($selector)->each(fn(Crawler $node): string => $this->normalizeText($node->text('')) ?? ''),
            fn(string $label): bool => $label !== '',
        ));
    }

    private function toInt(string $value): ?int
    {
        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) && $digits !== '' && is_numeric($digits) ? (int) $digits : null;
    }

    private function firstInt(string $value): ?int
    {
        return preg_match('/\d+/', $value, $matches) === 1 ? (int) $matches[0] : null;
    }
}
