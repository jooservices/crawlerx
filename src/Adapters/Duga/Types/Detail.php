<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Duga\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Support\SizeParser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * DUGA product page: the information table (dates, performers, director, maker, label, series, product ids,
 * categories, rankings), synopsis, rating, my-list count, price per version, file information, digest images and the
 * sample video.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const KNOWN_ROWS = ['配信開始日', '配信終了日', '発売日', '出演者', '監督', 'メーカー', 'レーベル', 'シリーズ', '作品ID', 'メーカー品番', '品番', 'カテゴリ', 'ランキング'];

    protected function siteLabel(): string
    {
        return 'DUGA';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        $externalId = $this->externalId($request->url);
        $sku = $this->cellText($rows, '作品ID');
        $productNumber = $this->cellText($rows, 'メーカー品番') ?? $this->cellText($rows, '品番');
        $prices = $this->prices($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId ?? $sku,
            title: $this->firstText($crawler, 'h1.title, h1[itemprop="name"]') ?? $externalId,
            data: MovieMeta::filter([
                'code' => $productNumber ?? $sku ?? $externalId,
                'cover_url' => $this->firstAttribute($crawler, 'meta[property="og:image"]', 'content'),
                'description' => $this->firstText($crawler, 'p.introduction'),
                'date' => $this->dateOf($rows, '発売日'),
                'duration' => $this->duration($crawler),
                'performers' => isset($rows['出演者']) ? $this->performers($rows['出演者'], $request->url) : [],
                'tags' => $this->categoryNames($rows),
                'screenshots' => $this->digest($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $this->cellText($rows, 'メーカー'),
                    MovieMeta::LABEL => $this->cellText($rows, 'レーベル'),
                    MovieMeta::SERIES => $this->cellText($rows, 'シリーズ'),
                    MovieMeta::DIRECTOR => isset($rows['監督']) ? implode(', ', $this->texts($rows['監督'], 'a')) : null,
                    MovieMeta::RATING => $this->rating($crawler),
                    MovieMeta::FAVORITE_COUNT => $this->count($this->firstText($crawler, '.mylistcount .count')),
                    MovieMeta::PRICE => $prices[0] ?? null,
                    'prices' => $prices,
                    MovieMeta::SAMPLE_VIDEO_URL => $this->firstAttribute($crawler, 'meta[property="og:video:url"]', 'content'),
                    MovieMeta::DOWNLOADS => $this->files($crawler),
                    MovieMeta::RELEASE_DATE_RAW => $this->cellText($rows, '発売日'),
                    'distribution_start_date' => $this->dateOf($rows, '配信開始日'),
                    'distribution_end_date' => $this->dateOf($rows, '配信終了日'),
                    'product_id' => $sku,
                    'maker_product_number' => $productNumber,
                    'maker_url' => isset($rows['メーカー']) ? $this->absoluteLink($rows['メーカー'], $request->url) : null,
                    'label_url' => isset($rows['レーベル']) ? $this->absoluteLink($rows['レーベル'], $request->url) : null,
                    'director_url' => isset($rows['監督']) ? $this->absoluteLink($rows['監督'], $request->url) : null,
                    'categories' => $this->categoryPaths($rows),
                    'rankings' => $this->rankings($rows, $request->url),
                    MovieMeta::FIELDS => $this->otherRows($rows),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'DUGA');

        return $item;
    }

    /** @return array<string, Crawler> information table label => value cell */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('table.infomation tr')->each(function (Crawler $row) use (&$rows): void {
            if ($row->filter('th')->count() === 0 || $row->filter('td')->count() === 0) {
                return;
            }

            $label = $this->normalizeText($row->filter('th')->first()->text(''));
            if ($label === null) {
                return;
            }

            // "メーカー品番" is written as <span class="ja-only">メーカー</span>品番; both spellings end up in the same key.
            $label = $label === '品番' ? 'メーカー品番' : $label;
            $rows[$label] ??= $row->filter('td')->first();
        });

        return $rows;
    }

    /** @param array<string, Crawler> $rows */
    private function cellText(array $rows, string $label): ?string
    {
        if (! isset($rows[$label])) {
            return null;
        }

        $text = $this->normalizeText($rows[$label]->text(''));

        return $text === null || preg_match('/^[-\x{2010}-\x{2015}ー－年月日]+$/u', $text) === 1 ? null : $text;
    }

    /** @param array<string, Crawler> $rows */
    private function dateOf(array $rows, string $label): ?string
    {
        if (! isset($rows[$label])) {
            return null;
        }

        $content = $rows[$label]->filter('[content]')->count() > 0 ? $rows[$label]->filter('[content]')->first()->attr('content') : null;

        return MovieMeta::isoDate(is_string($content) ? $content : null) ?? MovieMeta::isoDate($this->normalizeText($rows[$label]->text('')));
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $cell, string $baseUrl): array
    {
        $performers = [];
        $cell->filter('ul.performer a, a')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || isset($performers[$name])) {
                return;
            }

            $performers[$name] = MovieMeta::performer($name, null, $href === '' ? null : $this->absolute($baseUrl, $href));
        });

        return array_values($performers);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<string> the last element of every "Adult > Category" path
     */
    private function categoryNames(array $rows): array
    {
        $names = [];
        foreach ($this->categoryPaths($rows) as $path) {
            $names[] = end($path);
        }

        return array_values(array_unique(array_filter($names, static fn($name): bool => is_string($name) && $name !== '')));
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<list<string>>
     */
    private function categoryPaths(array $rows): array
    {
        if (! isset($rows['カテゴリ'])) {
            return [];
        }

        $paths = [];
        $rows['カテゴリ']->filter('li')->each(function (Crawler $item) use (&$paths): void {
            $path = $this->texts($item, 'a');
            if ($path !== []) {
                $paths[] = $path;
            }
        });

        return $paths;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<array{rank: ?int, name: string, url: ?string}>
     */
    private function rankings(array $rows, string $baseUrl): array
    {
        if (! isset($rows['ランキング'])) {
            return [];
        }

        $rankings = [];
        $rows['ランキング']->filter('.ranking')->each(function (Crawler $ranking) use (&$rankings, $baseUrl): void {
            $name = $this->firstText($ranking, 'a');
            if ($name === null) {
                return;
            }

            $rank = $this->firstText($ranking, '.no');
            $href = $this->firstAttribute($ranking, 'a', 'href');
            $rankings[] = ['rank' => $rank !== null && ctype_digit($rank) ? (int) $rank : null, 'name' => $name, 'url' => $href === null ? null : $this->absolute($baseUrl, $href)];
        });

        return $rankings;
    }

    private function absoluteLink(Crawler $cell, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($cell, 'a', 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }

    /** @return array{score: float, scale: ?float, votes: ?int}|null */
    private function rating(Crawler $crawler): ?array
    {
        $alt = $this->firstAttribute($crawler, '.ratingstar-total img', 'alt');
        if ($alt === null || ! is_numeric($alt)) {
            return null;
        }

        $reviews = $this->firstText($crawler, '.reviewcount-total');
        $votes = $reviews !== null && preg_match('/(\d+)/', $reviews, $match) === 1 ? (int) $match[1] : null;

        return MovieMeta::rating((float) $alt, 5.0, $votes);
    }

    private function count(?string $text): ?int
    {
        return $text !== null && preg_match('/([\d,]+)/', $text, $match) === 1 ? (int) str_replace(',', '', $match[1]) : null;
    }

    /** @return list<array{amount: ?float, currency: ?string, label: ?string}> */
    private function prices(Crawler $crawler): array
    {
        $prices = [];
        $crawler->filter('label.cartseltypebox')->each(function (Crawler $label) use (&$prices): void {
            $money = $this->firstText($label, '.money');
            $amount = $money !== null && preg_match('/([\d,]+)/', $money, $match) === 1 ? (float) str_replace(',', '', $match[1]) : null;
            if ($amount !== null) {
                $prices[] = MovieMeta::price($amount, 'JPY', $this->firstText($label, '.salestype'));
            }
        });

        return $prices;
    }

    private function duration(Crawler $crawler): ?int
    {
        $text = null;
        $crawler->filter('table.mediainfo tr')->each(function (Crawler $row) use (&$text): void {
            if ($text === null && $this->firstText($row, 'th') === '再生時間') {
                $text = $this->normalizeText($row->filter('td')->first()->text(''));
            }
        });

        return MovieMeta::durationMinutes($text);
    }

    /**
     * One entry per file block (HD, standard, streaming...) with the media information table.
     *
     * @return list<array<string, mixed>>
     */
    private function files(Crawler $crawler): array
    {
        $files = [];
        $crawler->filter('.file-rapper')->each(function (Crawler $block) use (&$files): void {
            if ($block->filter('table.mediainfo')->count() === 0) {
                return;
            }

            $info = [];
            $block->filter('table.mediainfo tr')->each(function (Crawler $row) use (&$info): void {
                $label = $this->firstText($row, 'th');
                $value = $row->filter('td')->count() > 0 ? $this->normalizeText($row->filter('td')->first()->text('')) : null;
                if ($label !== null && $value !== null) {
                    $info[$label] = $value;
                }
            });

            $size = $info['合計サイズ'] ?? null;
            $blockId = (string) $block->attr('id');
            $files[] = MovieMeta::filter([
                'type' => 'purchase',
                'edition' => str_starts_with($blockId, 'ppvhd') ? 'HD版' : (str_starts_with($blockId, 'ppv') ? '通常版' : null),
                'name' => $this->firstText($block, 'h3.filetype'),
                'quality' => $block->filter('.definition')->count() > 0 ? (string) $block->filter('.definition')->first()->attr('title') : null,
                'duration' => MovieMeta::durationMinutes($info['再生時間'] ?? null),
                'bitrate' => $info['ビットレート'] ?? null,
                'resolution' => $info['解像度'] ?? null,
                'files_count' => isset($info['ファイル数']) && preg_match('/(\d+)/', $info['ファイル数'], $m) === 1 ? (int) $m[1] : null,
                'size_label' => $size,
                'size_bytes' => SizeParser::bytes($size),
                'download_expiry' => $info['ダウンロード期限'] ?? null,
                'view_expiry' => $info['視聴期限'] ?? null,
                'protection' => $info['プロテクト'] ?? null,
                'format' => $info['ファイル形式'] ?? null,
            ]);
        });

        return $files;
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function digest(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter('#digestthumbbox li a[href]')->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = (string) $link->attr('href');
            if ($href === '' || isset($images[$href])) {
                return;
            }

            $thumb = $link->filter('img')->count() > 0 ? $link->filter('img')->first()->attr('src') : null;
            $images[$href] = [
                'url' => $this->absolute($baseUrl, $href),
                'thumbnail_url' => is_string($thumb) && $thumb !== '' ? $this->absolute($baseUrl, $thumb) : null,
                'position' => count($images),
            ];
        });

        return array_values($images);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, string>
     */
    private function otherRows(array $rows): array
    {
        $other = [];
        foreach ($rows as $label => $cell) {
            if (in_array($label, self::KNOWN_ROWS, true)) {
                continue;
            }

            $text = $this->normalizeText($cell->text(''));
            if ($text !== null) {
                $other[$label] = $text;
            }
        }

        return $other;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('~/ppv/([^/]+)/?$~i', $path, $match) === 1 ? $match[1] : null;
    }
}
