<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Comma\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Comma\DetailImages;
use JOOservices\CrawlerX\Adapters\Comma\DetailValues;
use JOOservices\CrawlerX\Adapters\Comma\Selectors;
use JOOservices\CrawlerX\Adapters\Comma\Site;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
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
        $root = $this->fetchCrawler($request)->filter(Selectors::DETAIL_ROOT);
        $title = $this->firstText($root, Selectors::DETAIL_TITLE);
        $rows = $this->rows($root);
        $code = $this->rowText($rows, '品番');
        if ($title === null || $code === null) {
            throw new CrawlParseException('COMMA VISION detail page did not contain a title and product code.');
        }

        $images = new DetailImages();
        $cover = $images->cover($root, $request->url);
        $comment = DetailValues::comment($this->rowText($rows, 'コメント'));
        $releaseRaw = $this->rowText($rows, '発売日');
        $specialSite = $this->firstAttribute($root, Selectors::DETAIL_SPECIAL_SITE, 'href');

        return MovieDto::item($request->url, $code, $title, [
            'code' => $code,
            'cover_url' => $cover,
            'description' => $comment['description'],
            'date' => DetailValues::releaseDate($releaseRaw),
            'duration' => MovieMeta::durationMinutes($this->rowText($rows, '収録時間')),
            'performers' => DetailValues::performers($this->rowText($rows, '出演者')),
            'tags' => $this->rowLinks($rows, 'タグ'),
            'screenshots' => $images->screenshots($root, $request->url, $cover),
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::NAME,
                MovieMeta::SERIES => $this->rowText($rows, 'シリーズ'),
                MovieMeta::DIRECTOR => $this->rowText($rows, '監督'),
                MovieMeta::PRICE => DetailValues::price($this->rowText($rows, '価格')),
                MovieMeta::RELEASE_DATE_RAW => $releaseRaw,
                MovieMeta::ORIGINAL_TITLE => $comment['original_title'],
                'special_video_duration' => MovieMeta::durationMinutes($this->rowText($rows, '特別映像')),
                'special_site_url' => $specialSite === null ? null : $this->absolute($request->url, $specialSite),
                'source_fields' => $this->sourceFields($rows),
            ]),
        ]);
    }

    /** @return array<string, Crawler> Each labelled row of the product section, keyed by its label. */
    private function rows(Crawler $root): array
    {
        $rows = [];
        $root->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, Selectors::ROW_LABEL);
            if ($label !== null) {
                $rows[$label] = $row;
            }
        });

        return $rows;
    }

    /** @param array<string, Crawler> $rows */
    private function rowText(array $rows, string $label): ?string
    {
        return isset($rows[$label]) ? $this->firstText($rows[$label], Selectors::ROW_VALUE) : null;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<string>
     */
    private function rowLinks(array $rows, string $label): array
    {
        return isset($rows[$label]) ? $this->texts($rows[$label], Selectors::ROW_VALUE . ' ' . Selectors::ROW_LINKS) : [];
    }

    /**
     * Every labelled row as the site shows it, for the fields that have no standard key.
     *
     * @param  array<string, Crawler>  $rows
     * @return array<string, string>
     */
    private function sourceFields(array $rows): array
    {
        $fields = [];
        foreach (array_keys($rows) as $label) {
            $value = $this->rowText($rows, $label);
            if ($value !== null) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }
}
