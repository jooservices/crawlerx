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
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

/** The BOMB.tv Plus index: one gallery item per idol, with every pack the index shows for her. */
final class Listing extends AbstractHtmlType implements TypeInterface
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
        return 'listing';
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $whatsNew = $this->whatsNew($crawler);

        /** @var array<string, array{name: string, name_image_url: ?string, packs: list<array<string, mixed>>, photos: list<PhotoDto>}> $idols */
        $idols = [];
        $crawler->filter(Selectors::LISTING_PACK_COVERS)->each(function (Crawler $cover) use (&$idols, $request, $whatsNew): void {
            $href = $this->ancestor($cover, 'a')->attr('href') ?? '';
            $idolId = $this->idolId($href);
            $image = $this->image($cover, $request->url);
            if ($idolId === null || $image === null) {
                return;
            }

            $table = $this->packTable($cover);
            $pack = $this->pack($table, $image, $href, $request->url, $whatsNew);
            $nameImage = $table->filter(Selectors::PACK_NAME_IMAGE);
            $idols[$idolId] ??= ['name' => $pack['name'], 'name_image_url' => null, 'packs' => [], 'photos' => []];
            $this->collectPhotos($idols[$idolId]['photos'], $pack['common'], $pack['images']);
            $idols[$idolId]['name_image_url'] ??= $nameImage->count() > 0 ? $this->image($nameImage->first(), $request->url)?->url : null;
            $idols[$idolId]['packs'][] = $pack['record'];
        });

        if ($idols === []) {
            throw new CrawlParseException('BOMB listing page did not contain any pack.');
        }

        $items = [];
        foreach ($idols as $idolId => $idol) {
            $photos = $idol['photos'];
            $items[] = GalleryDto::item(
                url: $this->absolute($request->url, '/plus/idolindex/' . $idolId . '.html'),
                externalId: $idolId,
                title: $idol['name'],
                data: [
                    'photo_count' => count($photos),
                    'performers' => [$idol['name']],
                    'photos' => $photos,
                    'metadata' => [
                        'name_image_url' => $idol['name_image_url'],
                        'pack_count' => count($idol['packs']),
                        'packs' => $idol['packs'],
                    ],
                ],
            )->withNextCrawlType('gallery');
        }

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'gallery',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: 1,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );
    }

    /**
     * @param array<string, array{label: string, text: ?string}> $whatsNew
     * @return array{name: string, record: array<string, mixed>, common: array<string, mixed>, images: list<array{0: PackImage, 1: array<string, mixed>, 2: ?string}>}
     */
    private function pack(Crawler $table, PackImage $cover, string $href, string $baseUrl, array $whatsNew): array
    {
        $alt = $cover->alt ?? '';
        $featured = preg_match('/^(.*?)\x{3000}No\.\s*(\d+)\x{3000}(.*)$/u', $alt, $featuredMatch) === 1;
        $name = $this->plain($featured ? $featuredMatch[1] : $alt) ?? $cover->id;
        $catchcopy = $this->nodeAlt($table, Selectors::LISTING_CATCHCOPY);
        $headline = $featured ? $this->plain($featuredMatch[3]) : $catchcopy;
        $number = $featured ? (int) $featuredMatch[2] : $this->packNumber($this->nodeAlt($table, Selectors::PACK_NUMBER_IMAGE));
        $packId = preg_match('/#([^#]+)$/', $href, $fragment) === 1 ? $fragment[1] : $cover->id;
        $contents = $this->lines($table, Selectors::PACK_CONTENTS);
        $productId = $this->productId($table);
        $thumbnail = $table->filter(Selectors::LISTING_THUMBNAIL);
        $thumbnailImage = $thumbnail->count() > 0 ? $this->image($thumbnail->first(), $baseUrl) : null;
        $catchcopyNode = $table->filter(Selectors::LISTING_CATCHCOPY);
        $catchcopyImage = $catchcopyNode->count() > 0 ? $this->image($catchcopyNode->first(), $baseUrl) : null;
        $samplePage = $this->samplePageUrl($baseUrl, $packId);
        $sampleImage = $this->sampleImageUrl($baseUrl, $packId);
        $news = $whatsNew[$packId] ?? null;

        $common = ['pack_id' => $packId, 'pack_number' => $number, 'model' => $name, 'source_page_url' => $baseUrl];
        $images = [
            [$cover, ['kind' => 'cover', 'caption' => $alt], $thumbnailImage?->url],
            [new PackImage('sample_' . $packId, $sampleImage), ['kind' => 'sample', 'caption' => $headline, 'source_page_url' => $samplePage], null],
        ];
        return [
            'name' => $name,
            'common' => $common,
            'images' => $images,
            'record' => [
                'id' => $packId,
                'number' => $number,
                'product_id' => $productId,
                'title' => $headline,
                'caption' => $alt === '' ? null : $alt,
                'catchcopy' => $catchcopy,
                'catchcopy_image_url' => $catchcopyImage?->url,
                'is_new' => $table->filter(Selectors::LISTING_NEW_BADGE)->count() > 0,
                ...$this->contentCounts($contents),
                'price_jpy' => $this->priceJpy($table),
                'access_days' => $this->accessDays($table),
                'purchase_url' => $this->purchaseUrl($productId),
                'sample_page_url' => $samplePage,
                'cover_url' => $cover->url,
                'thumbnail_url' => $thumbnailImage?->url,
                'sample_image_url' => $sampleImage,
                'whatsnew_label' => $news['label'] ?? null,
                'whatsnew_text' => $news['text'] ?? null,
                'source_fields' => $contents === null ? [] : ['contents' => $contents],
            ],
        ];
    }

    private function accessDays(Crawler $table): ?int
    {
        $price = $table->filter(Selectors::LISTING_PRICE_TEXT);

        return $price->count() > 0 && preg_match('#/\s*(\d+)\s*日間#u', $price->first()->text(''), $match) === 1
            ? (int) $match[1]
            : null;
    }

    /**
     * The "更新履歴" box: each entry is a link to a pack ("MM/DD name No.N追加！") followed by the blurb.
     *
     * @return array<string, array{label: string, text: ?string}> keyed by pack id (the link's #fragment)
     */
    private function whatsNew(Crawler $crawler): array
    {
        $box = $crawler->filter(Selectors::LISTING_WHATSNEW);
        if ($box->count() === 0) {
            return [];
        }

        $entries = [];
        $found = preg_match_all(
            '~<a href="[^"]*idolindex/[^"#]+\.html#([^"]+)">(.*?)</a>(.*?)(?=<a href="|\z)~su',
            $box->first()->html(''),
            $matches,
            PREG_SET_ORDER,
        );
        foreach ($found === false ? [] : $matches as $match) {
            $label = $this->plain(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $match[2]) ?? ''));
            if ($label !== null) {
                $entries[$match[1]] = ['label' => $label, 'text' => $this->plain(strip_tags(preg_replace('#<br\s*/?>#i', ' ', $match[3]) ?? ''))];
            }
        }

        return $entries;
    }
}
