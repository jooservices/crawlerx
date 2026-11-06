<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Deeps\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Deeps\Selectors;
use JOOservices\CrawlerX\Adapters\Deeps\Site;
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
        return 'DEEP\'S';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        $codeAndPrice = $this->codeAndPrice($rows['品番/価格'] ?? null);
        $released = isset($rows['発売日']) ? $this->cellText($rows['発売日']) : null;
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $screenshots = $this->screenshots($crawler, $request->url);
        $series = isset($rows['シリーズ']) ? $this->names($rows['シリーズ']) : [];

        $item = MovieDto::item($request->url, Site::externalId($request->url), $this->firstText($crawler, Selectors::DETAIL_TITLE), MovieMeta::filter([
            'code' => $codeAndPrice['code'],
            'cover_url' => $cover === null ? null : $this->absolute($request->url, $cover),
            'description' => $this->firstText($crawler, Selectors::DESCRIPTION),
            'date' => MovieMeta::isoDate($released),
            'duration' => isset($rows['収録時間']) ? MovieMeta::durationMinutes($this->cellText($rows['収録時間'])) : null,
            'performers' => isset($rows['出演']) ? $this->performers($rows['出演'], $request->url) : [],
            'tags' => isset($rows['カテゴリ']) ? $this->names($rows['カテゴリ']) : [],
            'screenshots' => $screenshots,
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::MAKER,
                MovieMeta::DIRECTOR => isset($rows['監督']) ? implode(', ', $this->names($rows['監督'])) : null,
                MovieMeta::SERIES => implode(', ', $series),
                MovieMeta::PRICE => $codeAndPrice['price'],
                MovieMeta::RELEASE_DATE_RAW => $released,
                MovieMeta::SAMPLE_VIDEO_URL => $this->firstAttribute($crawler, Selectors::SAMPLE_VIDEO, 'src'),
                'types' => isset($rows['タイプ・職業']) ? $this->names($rows['タイプ・職業']) : [],
                'purchase_links' => $this->purchaseLinks($crawler),
                'source_fields' => array_map($this->cellText(...), $rows),
            ]),
        ]));

        $this->assertUsableMovieDetail($item, 'DEEP\'S');

        return $item;
    }

    /** @return array<string, Crawler> label => value cell of the labelled rows */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, 'th');
            if ($label !== null && $row->filter('td')->count() > 0) {
                $rows[$label] = $row->filter('td')->first();
            }
        });

        return $rows;
    }

    /** The cells separate links with ideographic spaces and end with an empty placeholder link. */
    private function cellText(Crawler $cell): ?string
    {
        return $this->normalizeText(str_replace("\u{3000}", ' ', $cell->text('')));
    }

    /** @return list<string> */
    private function names(Crawler $cell): array
    {
        $names = $this->texts($cell, 'a');

        $text = $this->cellText($cell);

        return $names !== [] || $text === null ? $names : [$text];
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $cell, string $baseUrl): array
    {
        $performers = [];
        $cell->filter('a[href]')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            if ($name !== null) {
                $performers[$name] ??= MovieMeta::performer($name, null, $this->absolute($baseUrl, (string) $link->attr('href')));
            }
        });

        return array_values($performers);
    }

    /**
     * "MMGO-020 / 3,180 円（税抜）" is one cell: the product code and the list price.
     *
     * @return array{code: ?string, price: ?array{amount: ?float, currency: ?string, label: ?string}}
     */
    private function codeAndPrice(?Crawler $cell): array
    {
        $text = $cell === null ? null : $this->cellText($cell);
        if ($text === null || preg_match('#^([^/]+?)\s*(?:/\s*(.+))?$#u', $text, $match) !== 1) {
            return ['code' => null, 'price' => null];
        }

        $label = $match[2] ?? null;
        $amount = $label !== null && preg_match('/([\d,]+)\s*円/u', $label, $yen) === 1 ? (float) str_replace(',', '', $yen[1]) : null;

        return [
            'code' => $match[1],
            'price' => $label === null ? null : MovieMeta::price($amount, 'JPY', $label),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        $crawler->filter(Selectors::SAMPLE_IMAGES)->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
            $src = $this->firstAttribute($node, 'img[src]', 'src');
            $viewer = $this->firstAttribute($node, 'a[href]', 'href');
            if ($src !== null) {
                $screenshots[] = MovieMeta::filter([
                    'url' => $this->absolute($baseUrl, $src),
                    'viewer_url' => $viewer === null ? null : $this->absolute($baseUrl, $viewer),
                ]);
            }
        });

        return $screenshots;
    }

    /**
     * The "FANZA" shop buttons. The second button's href is missing its closing quote in the site's HTML, so the
     * attribute runs on into `target=`: only the first token is the URL.
     *
     * @return list<array{name: string, url: string}>
     */
    private function purchaseLinks(Crawler $crawler): array
    {
        $links = [];
        $crawler->filter(Selectors::PURCHASE_LINKS)->each(function (Crawler $link) use (&$links): void {
            $name = $this->normalizeText($link->text(''));
            $url = preg_split('/\s/', trim((string) $link->attr('href')))[0] ?? '';
            if ($name !== null && $url !== '') {
                $links[] = ['name' => $name, 'url' => $url];
            }
        });

        return $links;
    }
}
