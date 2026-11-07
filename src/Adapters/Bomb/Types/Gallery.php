<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bomb\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Bomb\Concerns\DecodesShiftJis;
use JOOservices\CrawlerX\Adapters\Bomb\Concerns\ParsesPacks;
use JOOservices\CrawlerX\Adapters\Bomb\PackImage;
use JOOservices\CrawlerX\Adapters\Bomb\Selectors;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/** An idol page of BOMB.tv Plus: every public image of each pack (cover, preview strips, sample) plus the pack facts. */
final class Gallery extends AbstractHtmlType implements TypeInterface
{
    use DecodesShiftJis;
    use ParsesHtml;
    use ParsesPacks;

    protected function siteLabel(): string
    {
        return 'bomb';
    }

    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $covers = $crawler->filter(Selectors::IDOL_PACK_COVER);
        $idolId = $this->idolId($request->url);
        if ($idolId === null || $covers->count() === 0) {
            throw new CrawlParseException('BOMB gallery page did not contain any pack.');
        }

        $nameImage = $this->nameImage($crawler, $request->url);
        $name = $this->splitName($nameImage?->alt)['name']
            ?? $this->plain(str_replace('BOMB.tvプラス', '', $this->firstText($crawler, Selectors::PAGE_TITLE) ?? ''));
        if ($name === null) {
            throw new CrawlParseException('BOMB gallery page did not contain an idol name.');
        }

        [$copyright, $notice] = $this->footer($crawler);
        $photos = [];
        $packs = [];
        $covers->each(function (Crawler $cover) use (&$photos, &$packs, $request, $name, $copyright): void {
            $image = $this->image($cover, $request->url);
            if ($image !== null) {
                $packs[] = $this->pack($cover, $image, $request->url, $name, $copyright, $photos);
            }
        });

        return GalleryDto::item(
            url: $request->url,
            externalId: $idolId,
            title: $name,
            data: [
                'photo_count' => count($photos),
                'performers' => [$name],
                'tags' => $this->keywords($crawler, $name),
                'photos' => $photos,
                'metadata' => [
                    'name_japanese' => $name,
                    'name_romaji' => $this->splitName($nameImage?->alt)['romaji'],
                    'name_image_url' => $nameImage?->url,
                    'keywords' => $this->keywords($crawler, null),
                    'pack_count' => count($packs),
                    'packs' => $packs,
                    'credit' => $copyright,
                    'license_notice' => $notice,
                ],
            ],
        );
    }

    /**
     * @param list<PhotoDto> $photos collects the pack's images in page order
     * @return array<string, mixed>
     */
    private function pack(Crawler $cover, PackImage $coverImage, string $baseUrl, string $name, ?string $credit, array &$photos): array
    {
        $table = $this->packTable($cover);
        $productId = $this->productId($table);
        $contents = $this->lines($table, Selectors::PACK_CONTENTS);
        $number = $this->packNumber($this->nodeAlt($table, Selectors::PACK_NUMBER_IMAGE));
        $caption = $coverImage->alt;
        $title = $caption === null ? null : $this->plain(preg_replace('/^' . preg_quote($name, '/') . '\x{3000}*/u', '', $caption) ?? '');
        $packId = $coverImage->id;
        $samplePage = $this->samplePageUrl($baseUrl, $packId);
        $sampleImage = $this->sampleImageUrl($baseUrl, $packId);
        $previews = $this->previewImages($table, $baseUrl);

        $common = ['pack_id' => $packId, 'pack_number' => $number, 'model' => $name, 'credit' => $credit];
        $images = [[$coverImage, ['kind' => 'cover', 'caption' => $caption, 'source_page_url' => $baseUrl], null]];
        foreach ($previews as $strip) {
            $images[] = [$strip, ['kind' => 'preview', 'caption' => $strip->alt, 'source_page_url' => $baseUrl], null];
        }
        $images[] = [
            new PackImage('sample_' . $packId, $sampleImage),
            ['kind' => 'sample', 'caption' => $title, 'source_page_url' => $samplePage],
            null,
        ];
        $this->collectPhotos($photos, $common, $images);

        return [
            'id' => $packId,
            'number' => $number,
            'product_id' => $productId,
            'title' => $title,
            'caption' => $caption,
            'description' => $this->lines($table, Selectors::PACK_DESCRIPTION),
            ...$this->contentCounts($contents),
            'price_jpy' => $this->priceJpy($table),
            'purchase_url' => $this->purchaseUrl($productId),
            'sample_page_url' => $samplePage,
            'cover_url' => $coverImage->url,
            'preview_urls' => array_map(static fn(PackImage $strip): string => $strip->url, $previews),
            'sample_image_url' => $sampleImage,
            'source_fields' => $contents === null ? [] : ['contents' => $contents],
        ];
    }

    private function nameImage(Crawler $crawler, string $baseUrl): ?PackImage
    {
        $node = $crawler->filter(Selectors::PACK_NAME_IMAGE);

        return $node->count() === 0 ? null : $this->image($node->first(), $baseUrl);
    }

    /** @return list<string> the page keywords; with a name the performer's own name parts and the site words are dropped */
    private function keywords(Crawler $crawler, ?string $name): array
    {
        $content = $this->firstAttribute($crawler, Selectors::META_KEYWORDS, 'content');
        $keywords = array_filter(array_map(trim(...), explode(',', $content ?? '')), static fn(string $word): bool => $word !== '');
        if ($name !== null) {
            $keywords = array_filter(
                $keywords,
                static fn(string $word): bool => ! str_contains($name, $word) && ! str_contains(strtolower($word), 'bomb') && $word !== 'ボム' && $word !== 'プラス',
            );
        }

        return array_values(array_unique($keywords));
    }

    /** @return array{0: ?string, 1: ?string} copyright holder and the usage notice of the page footer */
    private function footer(Crawler $crawler): array
    {
        $copyright = null;
        $notice = null;
        foreach (explode("\n", $this->lines($crawler, Selectors::FOOTER_COPYRIGHT) ?? '') as $line) {
            if (str_starts_with($line, '©')) {
                $copyright = $this->plain(substr($line, strlen('©')));
            } elseif ($line !== '') {
                $notice = $line;
            }
        }

        return [$copyright, $notice];
    }
}
