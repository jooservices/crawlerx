<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bomb\Concerns;

use JOOservices\CrawlerX\Adapters\Bomb\PackImage;
use JOOservices\CrawlerX\Adapters\Bomb\Selectors;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Shared parsing of the "packs" (paid photo/movie sets) that BOMB.tv Plus shows on its index and idol pages.
 *
 * @phpstan-require-extends \JOOservices\CrawlerX\Adapters\AbstractHtmlType
 */
trait ParsesPacks
{
    /** The exact link the site's own isp.js writes for its buy buttons (the site serves it over http). */
    private const PURCHASE_URL = 'http://www.bomb.tv/plus_src/select_payment.php?iid=%s&cid=plus';

    private function idolId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#(?:^|/)idolindex/([^/.]+)\.html$#i', $path, $match) === 1
            ? $match[1]
            : null;
    }

    /** The table that holds one whole pack block (cover, name, price, buttons, previews). */
    private function packTable(Crawler $cover): Crawler
    {
        return $this->ancestor($cover, 'table');
    }

    /** The closest ancestor element with the given tag name (an empty crawler when there is none). */
    private function ancestor(Crawler $node, string $tag): Crawler
    {
        $parent = $node->getNode(0)?->parentNode;
        while ($parent !== null && strtolower($parent->nodeName) !== $tag) {
            $parent = $parent->parentNode;
        }

        return $parent === null ? new Crawler() : new Crawler($parent);
    }

    private function image(Crawler $img, string $baseUrl): ?PackImage
    {
        $src = $img->attr('src');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        $width = $img->attr('width');
        $height = $img->attr('height');
        $alt = $img->attr('alt');

        return new PackImage(
            id: pathinfo($src, PATHINFO_FILENAME),
            url: $this->absolute($baseUrl, trim($src)),
            width: is_numeric($width) ? (int) $width : null,
            height: is_numeric($height) ? (int) $height : null,
            alt: is_string($alt) && trim($alt) !== '' ? trim($alt) : null,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function photo(PackImage $image, int $position, array $metadata, ?string $thumbnailUrl = null): PhotoDto
    {
        return new PhotoDto(
            id: $image->id,
            url: $image->url,
            imageUrl: $image->url,
            thumbnailUrl: $thumbnailUrl,
            position: $position,
            metadata: ['width' => $image->width, 'height' => $image->height, ...$metadata],
        );
    }

    /**
     * Appends the images that are not collected yet (by url) as photos numbered in page order.
     *
     * @param list<PhotoDto> $photos
     * @param array<string, mixed> $common metadata shared by every image
     * @param list<array{0: PackImage, 1: array<string, mixed>, 2: ?string}> $images image, its own metadata, thumbnail url
     */
    private function collectPhotos(array &$photos, array $common, array $images): void
    {
        $known = array_map(static fn(PhotoDto $photo): ?string => $photo->url, $photos);
        foreach ($images as [$image, $metadata, $thumbnailUrl]) {
            if (! in_array($image->url, $known, true)) {
                $photos[] = $this->photo($image, count($photos) + 1, [...$common, ...$metadata], $thumbnailUrl);
                $known[] = $image->url;
            }
        }
    }

    /** @return array{image_count: ?int, movie_count: ?int, voice_count: ?int} */
    private function contentCounts(?string $contents): array
    {
        $count = static fn(string $pattern): ?int => $contents !== null && preg_match($pattern, $contents, $match) === 1
            ? (int) $match[1]
            : null;

        return [
            'image_count' => $count('/画像\s*(\d+)\s*点/u'),
            'movie_count' => $count('/ムービー\s*(\d+)\s*本/u') ?? ($contents !== null && str_contains($contents, 'ムービー') ? 0 : null),
            'voice_count' => $count('/システムボイス\s*(\d+)\s*点/u'),
        ];
    }

    private function productId(Crawler $table): ?string
    {
        return preg_match("/(?:btn_buy\w*|link_buy_i)\('(p_[^']+)'/", $table->html(''), $match) === 1 ? $match[1] : null;
    }

    private function priceJpy(Crawler $table): ?int
    {
        return preg_match('/(?:print_kakaku\(|kakaku)(\d+)/', $table->html(''), $match) === 1 ? (int) $match[1] : null;
    }

    private function purchaseUrl(?string $productId): ?string
    {
        return $productId === null ? null : sprintf(self::PURCHASE_URL, $productId);
    }

    /** The page the site's `photodisp('<pack>')` script opens in a pop-up. */
    private function samplePageUrl(string $baseUrl, string $packId): string
    {
        return $this->absolute($baseUrl, '/plus/sample/' . $packId . '.html');
    }

    /** The public sample picture that pop-up shows. */
    private function sampleImageUrl(string $baseUrl, string $packId): string
    {
        return $this->absolute($baseUrl, '/plus/sample/images/' . $packId . '.jpg');
    }

    private function packNumber(?string $label): ?int
    {
        return $label !== null && preg_match('/No\.\s*(\d+)/i', $label, $match) === 1 ? (int) $match[1] : null;
    }

    private function nodeAlt(Crawler $scope, string $selector): ?string
    {
        $node = $scope->filter($selector);
        $alt = $node->count() > 0 ? $node->first()->attr('alt') : null;

        return is_string($alt) ? $this->plain($alt) : null;
    }

    /** Text of the first match with `<br>` kept as line breaks. */
    private function lines(Crawler $scope, string $selector): ?string
    {
        $node = $scope->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $text = preg_replace('#<br\s*/?>#i', "\n", $node->first()->html('')) ?? '';
        $lines = array_filter(array_map(
            fn(string $line): string => $this->plain(strip_tags($line)) ?? '',
            explode("\n", $text),
        ), static fn(string $line): bool => $line !== '');

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function plain(string $text): ?string
    {
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));

        return $text === '' ? null : $text;
    }

    /** @return array{name: ?string, romaji: ?string} the banner alt "三田悠貴　MITA YUUKI" (ideographic space separated) */
    private function splitName(?string $alt): array
    {
        $parts = $alt === null ? [] : preg_split('/\x{3000}+/u', $alt, 2);
        $parts = is_array($parts) ? $parts : [];

        return ['name' => $this->plain($parts[0] ?? ''), 'romaji' => $this->plain($parts[1] ?? '')];
    }

    /** @return list<PackImage> */
    private function previewImages(Crawler $table, string $baseUrl): array
    {
        return array_values(array_filter(
            $table->filter(Selectors::PACK_PREVIEW_STRIPS)->each(fn(Crawler $img): ?PackImage => $this->image($img, $baseUrl)),
        ));
    }
}
