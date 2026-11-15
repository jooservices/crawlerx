<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Digitalark\DetailFields;
use JOOservices\CrawlerX\Adapters\Digitalark\Selectors;
use JOOservices\CrawlerX\Adapters\Digitalark\Site;
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
        $id = Site::itemId($request->url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        if ($id === null || $title === null) {
            throw new CrawlParseException('DIGITAL ARK detail page did not contain an item ID and title.');
        }

        $fields = new DetailFields();
        $rows = $fields->labelled($crawler);
        $cover = $this->cover($crawler, $request->url);
        $releaseDate = $rows['発売日'] ?? null;

        $item = MovieDto::item($request->url, $id, $title, [
            'code' => $id,
            'cover_url' => $cover,
            'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
            'date' => MovieMeta::isoDate($releaseDate),
            'duration' => MovieMeta::durationMinutes($rows['収録時間'] ?? null),
            'tags' => $fields->keywords($crawler),
            'screenshots' => $cover === null ? [] : [['url' => $cover, 'thumbnail_url' => null]],
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::NAME,
                MovieMeta::DIRECTOR => $rows['監督'] ?? null,
                MovieMeta::LABEL => $rows['レーベル'] ?? null,
                MovieMeta::RELEASE_DATE_RAW => $releaseDate,
                MovieMeta::PRICE => $fields->price($crawler),
                'product_id' => $id,
                'product_code' => $rows['商品コード'] ?? null,
                'source_fields' => $rows,
            ]),
        ]);

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    private function cover(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }
}
