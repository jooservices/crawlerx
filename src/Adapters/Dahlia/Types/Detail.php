<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Dahlia\Fields;
use JOOservices\CrawlerX\Adapters\Dahlia\Selectors;
use JOOservices\CrawlerX\Adapters\Dahlia\Site;
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

    private const ROW_RELEASE_DATE = '発売日';

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
        $externalId = Site::workId($request->url);
        $rows = $fields->rows($crawler, Selectors::DETAIL_ROWS);
        $releaseDate = MovieMeta::isoDate($rows[self::ROW_RELEASE_DATE] ?? null);
        $distributionDate = MovieMeta::isoDate($rows[self::ROW_DISTRIBUTION_DATE] ?? null);
        $paragraphs = $this->texts($crawler, Selectors::DETAIL_DESCRIPTION);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, Selectors::DETAIL_TITLE),
            data: [
                'code' => $externalId === null ? null : Site::code($externalId),
                'cover_url' => $fields->image($crawler, Selectors::DETAIL_COVER, $request->url),
                'description' => $paragraphs === [] ? null : implode("\n", $paragraphs),
                'date' => $releaseDate ?? $distributionDate,
                'duration' => MovieMeta::durationMinutes($rows[self::ROW_DURATION] ?? null),
                'performers' => $fields->cast($rows[self::ROW_CAST] ?? null),
                'screenshots' => $this->screenshots($crawler, $request->url, $fields),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => Site::NAME,
                    'release_date' => $releaseDate,
                    'distribution_date' => $distributionDate,
                    'release_notes' => $fields->releaseNotes($crawler, Selectors::DETAIL_RELEASE_NOTES),
                    MovieMeta::SAMPLE_VIDEO_URL => $fields->link($crawler, Selectors::DETAIL_SAMPLE_VIDEO, $request->url),
                    'purchase_links' => $fields->purchaseLinks($crawler, Selectors::DETAIL_PURCHASE_LINKS),
                    'source_fields' => $rows,
                ]),
            ],
        );

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /**
     * Every sample still of the gallery: the original image and its 400x400 thumbnail, in page order.
     *
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl, Fields $fields): array
    {
        $screenshots = [];
        $crawler->filter(Selectors::DETAIL_GALLERY)->each(function (Crawler $link) use (&$screenshots, $baseUrl, $fields): void {
            $href = trim((string) $link->attr('href'));
            $url = $href === '' ? null : $this->absolute($baseUrl, $href);
            if ($url === null || isset($screenshots[$url])) {
                return;
            }

            $screenshots[$url] = [
                'url' => $url,
                'thumbnail_url' => $fields->link($link, 'img', $baseUrl, 'src'),
                'position' => count($screenshots) + 1,
            ];
        });

        return array_values($screenshots);
    }
}
