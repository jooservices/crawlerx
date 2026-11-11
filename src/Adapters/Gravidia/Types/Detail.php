<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravidia\Types;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Gravidia\Selectors;
use JOOservices\CrawlerX\Adapters\Gravidia\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
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
        $root = $crawler->filter(Selectors::DETAIL_ROOT);
        $id = Site::productId($request->url);
        $title = $this->firstText($root, Selectors::DETAIL_TITLE);
        if ($id === null || $title === null) {
            throw new CrawlParseException('Gravidia detail page did not contain a product ID and title.');
        }

        $cover = $this->image($root, Selectors::DETAIL_COVER, $request->url);
        $rows = $this->sourceFields($root);
        $date = MovieMeta::isoDate($rows['発売日'] ?? null);
        $model = $this->firstText($root, Selectors::DETAIL_MODEL);
        $modelUrl = $this->firstAttribute($root, Selectors::DETAIL_MODEL, 'href');
        $tags = $this->texts($root, Selectors::DETAIL_TAGS);
        $price = $this->firstText($root, Selectors::DETAIL_PRICE);
        $preview = $this->firstAttribute($root, Selectors::DETAIL_PREVIEW, 'data-tameshiyomi-url');
        $publishedAt = $this->metaContent($crawler, 'article:published_time');

        $item = MovieDto::item($request->url, $id, $title, [
            'code' => $id,
            'cover_url' => $cover,
            'description' => $this->firstText($root, Selectors::DETAIL_DESCRIPTION),
            'date' => $date,
            'performers' => $model === null ? [] : [$model],
            'tags' => $tags,
            'screenshots' => $cover === null ? [] : [['url' => $cover, 'thumbnail_url' => null]],
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::NAME,
                'product_id' => $id,
                'model' => $model,
                'model_url' => $modelUrl === null ? null : $this->absolute($request->url, $modelUrl),
                'photographer' => $this->rowValue($rows, '撮影'),
                'label' => $this->rowValue($rows, 'シリーズ') === null ? null : $tags[0] ?? null,
                'series' => $this->rowValue($rows, 'シリーズ'),
                'release_date_raw' => $rows['発売日'] ?? null,
                'posted_date' => $this->siteDate($publishedAt),
                'published_at' => $publishedAt,
                'price' => $price,
                'preview_url' => $preview === null ? null : $this->absolute($request->url, $preview),
                'source_fields' => $rows,
            ]),
        ]);

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, string> */
    private function sourceFields(Crawler $root): array
    {
        $fields = [];
        $root->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$fields): void {
            $label = $this->firstText($row, '.p-ProductDetail_MetaTitle');
            if ($label === null) {
                return;
            }

            $label = rtrim($label, "：:");
            $value = trim(str_replace($this->firstText($row, '.p-ProductDetail_MetaTitle') ?? '', '', $row->text('')));
            if ($value !== '') {
                $fields[$label] = $value;
            }
        });

        return $fields;
    }

    /** @param array<string, string> $rows */
    private function rowValue(array $rows, string $label): ?string
    {
        return $rows[$label] ?? null;
    }

    private function image(Crawler $node, string $selector, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($node, $selector, 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    private function metaContent(Crawler $crawler, string $property): ?string
    {
        $meta = $crawler->filter('meta[property="' . $property . '"]');

        return $meta->count() > 0 ? $meta->first()->attr('content') : null;
    }

    private function siteDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d');
        } catch (Exception) {
            return null;
        }
    }
}
