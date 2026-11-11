<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fairway\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Fairway\Site;
use JOOservices\CrawlerX\Adapters\Fairway\Selectors;
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
        $body = $crawler->filter(Selectors::DETAIL_BODY);
        $rows = $this->rows($body);
        $formats = $this->formats($rows);
        $genres = $this->links($rows['ジャンル'] ?? null);
        $images = $this->images($crawler);
        $externalId = Site::externalId($request->url);
        $releaseDate = MovieMeta::isoDate($this->cleanRow($rows['発売日'] ?? null));
        $performers = $this->texts($rows['女優'] ?? new Crawler(), '.td .item p');
        $video = $this->firstAttribute($body, 'video', 'src');
        $poster = $this->firstAttribute($body, 'video', 'poster');
        $sourceFields = array_map(
            static fn(Crawler $row): string => trim($row->text('')),
            $rows,
        );

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($body, Selectors::DETAIL_TITLE),
            data: [
                'code' => $formats[0]['code'] ?? $externalId,
                'description' => $this->firstText($body, Selectors::DETAIL_DESCRIPTION),
                'date' => $releaseDate,
                'duration' => $formats[0]['duration'] ?? null,
                'performers' => array_map(static fn(string $name): array => MovieMeta::performer($name), $performers),
                'tags' => array_column($genres, 'name'),
                'cover_url' => $images[0] ?? null,
                'screenshots' => $images,
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::SERIES => $this->cleanRow($rows['シリーズ'] ?? null),
                    MovieMeta::DIRECTOR => $this->cleanRow($rows['監督'] ?? null),
                    'genres' => $genres,
                    'formats' => $formats,
                    MovieMeta::SAMPLE_VIDEO_URL => $video === null ? null : $this->absolute($request->url, $video),
                    'sample_video_poster_url' => $poster === null ? null : $this->absolute($request->url, $poster),
                    'purchase_links' => $this->purchaseLinks($body, $request->url),
                    'source_fields' => $sourceFields,
                ]),
            ],
        );
        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, Crawler> */
    private function rows(Crawler $body): array
    {
        $rows = [];
        $body->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, '.th');
            if ($label !== null) {
                $rows[$label] = $row;
            }
        });

        return $rows;
    }

    /** @param array<string, Crawler> $rows
     *  @return list<array{format: string, code?: string, duration?: int, price?: array{amount: ?float, currency: ?string, label: ?string}}>
     */
    private function formats(array $rows): array
    {
        $formats = [];
        foreach (['品番' => 'code', '収録時間' => 'duration', '価格' => 'price'] as $label => $field) {
            if (! isset($rows[$label])) {
                continue;
            }

            foreach ($rows[$label]->filter('.td .item') as $element) {
                $item = new Crawler($element);
                $format = $this->firstText($item, 'span');
                $value = trim(str_replace((string) $format, '', $item->text('')));
                if ($format === null || $value === '') {
                    continue;
                }

                $formats[$format] ??= ['format' => $format];
                if ($field === 'code') {
                    $formats[$format]['code'] = $value;
                } elseif ($field === 'duration') {
                    $duration = MovieMeta::durationMinutes($value);
                    if ($duration !== null) {
                        $formats[$format]['duration'] = $duration;
                    }
                } else {
                    preg_match('/([\d,]+)\s*円/u', $value, $match);
                    $amount = isset($match[1]) ? (float) str_replace(',', '', $match[1]) : null;
                    $formats[$format]['price'] = MovieMeta::price($amount, 'JPY', $value);
                }
            }
        }

        return array_values($formats);
    }

    /** @return list<array{name: string, url: string, external_id: string}> */
    private function links(?Crawler $row): array
    {
        if ($row === null) {
            return [];
        }

        return $row->filter('.td a[href]')->each(function (Crawler $link): array {
            $url = $this->absolute(Site::BASE_URL, (string) $link->attr('href'));

            return [
                'name' => $this->normalizeText($link->text('')) ?? '',
                'url' => $url,
                'external_id' => basename((string) parse_url($url, PHP_URL_PATH)),
            ];
        });
    }

    /** @return list<string> */
    private function images(Crawler $crawler): array
    {
        $images = [];
        $crawler->filter(Selectors::DETAIL_GALLERY)->each(function (Crawler $slide) use (&$images): void {
            $url = $this->firstAttribute($slide, 'img', 'data-src') ?? $this->firstAttribute($slide, 'img', 'src');
            if ($url !== null) {
                $url = $this->absolute(Site::BASE_URL, $url);
                $images[$url] = $url;
            }
        });

        return array_values($images);
    }

    /** @return list<array{store: string, label: string, url: string}> */
    private function purchaseLinks(Crawler $body, string $baseUrl): array
    {
        return $body->filter('.p-workPage__side a.box[href]')->each(function (Crawler $link) use ($baseUrl): array {
            $label = $this->firstText($link, '.buy') ?? $this->normalizeText($link->text('')) ?? '';
            $href = (string) $link->attr('href');

            return ['store' => $label, 'label' => $label, 'url' => $this->absolute($baseUrl, $href)];
        });
    }

    private function cleanRow(?Crawler $row): ?string
    {
        if ($row === null) {
            return null;
        }

        return $this->normalizeText($row->filter('.td')->text(''));
    }
}
