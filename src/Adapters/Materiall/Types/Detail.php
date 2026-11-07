<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Materiall\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Falenogroup\Fields as StoreFields;
use JOOservices\CrawlerX\Adapters\Materiall\Fields;
use JOOservices\CrawlerX\Adapters\Materiall\Selectors;
use JOOservices\CrawlerX\Adapters\Materiall\Site;
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

    private const ROW_DIRECTOR = '監督';

    private const ROW_DURATION = '収録時間';

    private const ROW_RESERVATION_DATE = '予約開始日';

    private const ROW_DISTRIBUTION_DATE = '配信開始日';

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
        $reservation = MovieMeta::isoDate($rows[self::ROW_RESERVATION_DATE] ?? null);
        $distribution = MovieMeta::isoDate($rows[self::ROW_DISTRIBUTION_DATE] ?? null);
        $cover = $fields->lazyImage($crawler, Selectors::DETAIL_COVER);
        $sample = $this->firstAttribute($crawler, Selectors::DETAIL_SAMPLE_VIDEO, 'href');
        $title = Site::cleanTitle($this->firstAttribute($crawler, Selectors::DETAIL_OG_TITLE, 'content'))
            ?? $this->firstText($crawler, Selectors::DETAIL_TITLE);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $externalId === null ? null : Site::code($externalId),
                'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
                'description' => $this->description($crawler),
                'date' => $distribution ?? $reservation,
                'duration' => MovieMeta::durationMinutes($rows[self::ROW_DURATION] ?? null),
                'performers' => array_map(
                    static fn(string $name): array => MovieMeta::performer($name),
                    Site::castNames($rows[self::ROW_CAST] ?? null),
                ),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    MovieMeta::DIRECTOR => $rows[self::ROW_DIRECTOR] ?? null,
                    'reservation_start_date' => $reservation,
                    'distribution_date' => $distribution,
                    MovieMeta::SAMPLE_VIDEO_URL => $sample === null ? null : $this->absolute($request->url, $sample),
                    'purchase_links' => (new StoreFields())->purchaseLinks($crawler, Selectors::DETAIL_PURCHASE_LINKS),
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
}
