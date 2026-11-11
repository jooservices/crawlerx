<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Silklabo\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Silklabo\Fields;
use JOOservices\CrawlerX\Adapters\Silklabo\Selectors;
use JOOservices\CrawlerX\Adapters\Silklabo\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return Site::NAME;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $fields = (new Fields())->labelledFields($crawler->filter(Selectors::DETAIL_TEXT));
        $externalId = Site::externalId($request->url);
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER_LINK, 'href');
        $coverThumbnail = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $releaseDate = MovieMeta::isoDate($fields['発売日'] ?? null);
        $director = $fields['監督'] ?? null;
        $performers = $this->performers($crawler->filter(Selectors::DETAIL_TEXT));

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $fields['商品名'] ?? null,
            data: MovieMeta::filter([
                'code' => $fields['品番'] ?? null,
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'description' => $fields['商品説明'] ?? null,
                'date' => $releaseDate,
                'duration' => null,
                'performers' => array_map(static fn(string $name): array => MovieMeta::performer($name), $performers),
                'screenshots' => $this->gallery($crawler, $request->url, $cover, $coverThumbnail),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::DIRECTOR => $director,
                    'release_date' => $releaseDate,
                    'cover_thumbnail_url' => $coverThumbnail === null ? null : $this->absolute($request->url, $coverThumbnail),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->firstAttribute($crawler, 'iframe[src*="litevideo"]', 'src'),
                    'purchase_links' => $this->purchaseLinks($crawler, $request->url),
                    'source_fields' => $fields,
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return list<string> */
    private function performers(Crawler $scope): array
    {
        $performers = [];
        $scope->filter('a[href*="/product_actor/"]')->each(function (Crawler $link) use (&$performers): void {
            $name = $this->normalizeText($link->text(''));
            if ($name !== null) {
                $performers[] = $name;
            }
        });

        return array_values(array_unique($performers));
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function gallery(Crawler $crawler, string $baseUrl, ?string $cover, ?string $coverThumbnail): array
    {
        $images = [];
        if ($cover !== null) {
            $url = $this->absolute($baseUrl, $cover);
            $images[$url] = [
                'url' => $url,
                'thumbnail_url' => $coverThumbnail === null ? null : $this->absolute($baseUrl, $coverThumbnail),
                'position' => 1,
            ];
        }

        $crawler->filter(Selectors::DETAIL_GALLERY)->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = trim((string) $link->attr('href'));
            if ($href === '') {
                return;
            }

            $url = $this->absolute($baseUrl, $href);
            if (isset($images[$url])) {
                return;
            }

            $thumbnail = $this->firstAttribute($link, 'img', 'src');
            $images[$url] = [
                'url' => $url,
                'thumbnail_url' => $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                'position' => count($images) + 1,
            ];
        });

        return array_values($images);
    }

    /** @return list<array{name: string, url: string}> */
    private function purchaseLinks(Crawler $crawler, string $baseUrl): array
    {
        $links = [];
        $crawler->filter('.detail-info .btn-wrap a[href]')->each(function (Crawler $link) use (&$links, $baseUrl): void {
            $href = trim((string) $link->attr('href'));
            $name = $this->normalizeText($link->text(''));
            if ($href !== '' && $name !== null) {
                $url = $this->absolute($baseUrl, $href);
                $links[$url] = ['name' => $name, 'url' => $url];
            }
        });

        return array_values($links);
    }
}
