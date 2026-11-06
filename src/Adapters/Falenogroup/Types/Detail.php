<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Falenogroup\Fields;
use JOOservices\CrawlerX\Adapters\Falenogroup\Selectors;
use JOOservices\CrawlerX\Adapters\Falenogroup\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const ROW_CAST = '出演女優';

    private const ROW_DURATION = '収録時間';

    private const ROW_DIRECTOR = '監督';

    private const ROW_MAKER = 'メーカー';

    private const ROW_DISTRIBUTION_DATE = '配信開始日';

    private const ROW_RELEASE_DATE = '発売日';

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
        $fields = new Fields();
        $externalId = Site::externalId($request->url);
        $rows = $this->rows($crawler);
        $releaseDate = MovieMeta::isoDate($rows[self::ROW_RELEASE_DATE] ?? null);
        $distributionDate = MovieMeta::isoDate($rows[self::ROW_DISTRIBUTION_DATE] ?? null);
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $sample = $this->firstAttribute($crawler, Selectors::DETAIL_SAMPLE_VIDEO, 'href');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, Selectors::DETAIL_TITLE),
            data: [
                'code' => $externalId === null ? null : Site::code($externalId),
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'description' => $this->description($crawler),
                'date' => $releaseDate ?? $distributionDate,
                'duration' => MovieMeta::durationMinutes($rows[self::ROW_DURATION] ?? null),
                'performers' => $this->performers($rows[self::ROW_CAST] ?? null),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $rows[self::ROW_MAKER] ?? null,
                    MovieMeta::DIRECTOR => $rows[self::ROW_DIRECTOR] ?? null,
                    'release_date' => $releaseDate,
                    'distribution_date' => $distributionDate,
                    'release_note' => $fields->releaseNote($crawler, Selectors::DETAIL_RELEASE_NOTE),
                    MovieMeta::SAMPLE_VIDEO_URL => $sample === null ? null : $this->absolute($request->url, $sample),
                    'purchase_links' => $fields->purchaseLinks($crawler, Selectors::DETAIL_PURCHASE_LINKS),
                    'source_fields' => array_filter($rows, static fn(string $value): bool => $value !== ''),
                ]),
            ],
        );

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /**
     * Labelled rows of the work box. Rows are repeated for the time-gated variants of the page, so the first
     * non-empty value of a label wins.
     *
     * @return array<string, string>
     */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, 'span');
            if ($label === null) {
                return;
            }

            $rows[$label] = ($rows[$label] ?? '') !== '' ? $rows[$label] : ($this->firstText($row, 'p') ?? '');
        });

        return $rows;
    }

    private function description(Crawler $crawler): ?string
    {
        $paragraphs = $this->texts($crawler, Selectors::DETAIL_DESCRIPTION);

        return $paragraphs === [] ? null : implode("\n", $paragraphs);
    }

    /**
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(?string $cast): array
    {
        $names = preg_split('#\s*[、,，/／]\s*#u', (string) $cast, -1, PREG_SPLIT_NO_EMPTY);

        return array_map(static fn(string $name): array => MovieMeta::performer($name), $names === false ? [] : $names);
    }

    /**
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        $crawler->filter(Selectors::DETAIL_GALLERY)->each(function (Crawler $link) use (&$screenshots, $baseUrl): void {
            $href = trim((string) $link->attr('href'));
            $thumbnail = $this->firstAttribute($link, 'img', 'src');
            if ($href === '' || isset($screenshots[$href])) {
                return;
            }

            $screenshots[$href] = [
                'url' => $this->absolute($baseUrl, $href),
                'thumbnail_url' => $thumbnail === null ? null : $this->absolute($baseUrl, $thumbnail),
                'position' => count($screenshots) + 1,
            ];
        });

        return array_values($screenshots);
    }
}
