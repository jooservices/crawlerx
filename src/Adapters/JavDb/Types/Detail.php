<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDb\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * JavDB movie page: info panel rows (ID, date, duration, director, maker, series, rating, tags, actors), the
 * magnet list, preview images and the want/seen counters. Related-movie tiles ("You may also like") are not data.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    /** Row labels in the other site languages (zh-TW and zh-CN), mapped to the English label used below. */
    private const LABEL_ALIASES = [
        '番號' => 'ID', '番号' => 'ID',
        '日期' => 'Released Date',
        '時長' => 'Duration', '时长' => 'Duration',
        '導演' => 'Director', '导演' => 'Director',
        '片商' => 'Maker',
        '發行商' => 'Publisher', '发行商' => 'Publisher',
        '系列' => 'Series',
        '評分' => 'Rating', '评分' => 'Rating',
        '類別' => 'Tags', '类别' => 'Tags',
        '演員' => 'Actor(s)', '演员' => 'Actor(s)',
    ];

    private const KNOWN_ROWS = ['ID', 'Released Date', 'Duration', 'Director', 'Maker', 'Publisher', 'Label', 'Series', 'Rating', 'Tags', 'Actor(s)'];

    protected function siteLabel(): string
    {
        return 'JavDB';
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
        $code = $this->code($crawler, $rows);
        $title = $this->title($crawler, $code) ?? $code ?? $externalId;
        [$wantCount, $watchedCount] = $this->counters($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: MovieMeta::filter([
                'code' => $code,
                'cover_url' => $this->cover($crawler, $request->url),
                'date' => MovieMeta::isoDate($this->rowText($rows, 'Released Date')),
                'duration' => MovieMeta::durationMinutes($this->rowText($rows, 'Duration')),
                'performers' => $this->performers($rows, $request->url),
                'tags' => isset($rows['Tags']) ? $this->texts($rows['Tags'], 'a') : [],
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $this->rowText($rows, 'Maker') ?? $this->rowText($rows, 'Publisher'),
                    MovieMeta::LABEL => $this->rowText($rows, 'Label'),
                    MovieMeta::SERIES => $this->rowText($rows, 'Series'),
                    MovieMeta::DIRECTOR => $this->rowText($rows, 'Director'),
                    MovieMeta::RATING => $this->rating($this->rowText($rows, 'Rating')),
                    MovieMeta::MAGNETS => $this->magnets($crawler),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($crawler, $request->url),
                    MovieMeta::RELEASE_DATE_RAW => $this->rowText($rows, 'Released Date'),
                    'want_count' => $wantCount,
                    'watched_count' => $watchedCount,
                    MovieMeta::FIELDS => $this->otherRows($rows),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'JavDB');

        return $item;
    }

    /** @return array<string, Crawler> info panel label => value cell */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('.movie-panel-info .panel-block')->each(function (Crawler $block) use (&$rows): void {
            if ($block->filter('strong')->count() === 0 || $block->filter('.value')->count() === 0) {
                return;
            }

            $label = $this->normalizeText($block->filter('strong')->first()->text(''));
            $label = $label === null ? null : rtrim($label, ': ');
            $label = $label === null ? null : (self::LABEL_ALIASES[$label] ?? $label);
            if ($label !== null && $label !== '' && ! isset($rows[$label])) {
                $rows[$label] = $block->filter('.value')->first();
            }
        });

        return $rows;
    }

    /** @param array<string, Crawler> $rows */
    private function rowText(array $rows, string $label): ?string
    {
        if (! isset($rows[$label])) {
            return null;
        }

        $links = $this->texts($rows[$label], 'a');

        return $links !== [] ? implode(', ', $links) : $this->normalizeText($rows[$label]->text(''));
    }

    /** @param array<string, Crawler> $rows */
    private function code(Crawler $crawler, array $rows): ?string
    {
        $copied = $this->firstAttribute($crawler, '.movie-panel-info [data-clipboard-text]', 'data-clipboard-text');

        return $copied ?? $this->rowText($rows, 'ID');
    }

    private function title(Crawler $crawler, ?string $code): ?string
    {
        $title = $this->firstText($crawler, 'h2.title strong.current-title');
        if ($title !== null) {
            return $title;
        }

        $heading = $this->firstText($crawler, 'h2.title');

        return $heading !== null && $code !== null ? trim(str_replace($code, '', $heading)) : $heading;
    }

    private function cover(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, 'img.video-cover', 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<array{external_id: ?string, name: string, url: ?string, gender?: string}>
     */
    private function performers(array $rows, string $baseUrl): array
    {
        if (! isset($rows['Actor(s)'])) {
            return [];
        }

        $performers = [];
        $rows['Actor(s)']->filter('a[href^="/actors/"]')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || isset($performers[$href])) {
                return;
            }

            $symbol = $link->nextAll()->filter('strong.symbol')->first();
            $class = $symbol->count() > 0 ? (string) $symbol->attr('class') : '';
            $gender = str_contains($class, 'female') ? 'female' : (str_contains($class, 'male') ? 'male' : null);
            $id = basename((string) (parse_url($href, PHP_URL_PATH) ?? $href));

            $performers[$href] = MovieMeta::performer($name, $id, $this->absolute($baseUrl, $href)) + ($gender === null ? [] : ['gender' => $gender]);
        });

        return array_values($performers);
    }

    /** @return array{score: float, scale: ?float, votes: ?int}|null */
    private function rating(?string $text): ?array
    {
        if ($text !== null && preg_match('/(\d+(?:\.\d+)?)\s*,\s*by\s+([\d,]+)\s+users?/i', $text, $match) === 1) {
            return MovieMeta::rating((float) $match[1], 5.0, (int) str_replace(',', '', $match[2]));
        }

        if ($text !== null && preg_match('/(\d+(?:\.\d+)?)\s*分\s*,?\s*由\s*([\d,]+)\s*人/u', $text, $match) === 1) {
            return MovieMeta::rating((float) $match[1], 5.0, (int) str_replace(',', '', $match[2]));
        }

        return null;
    }

    /** @return array{0: ?int, 1: ?int} want-to-watch and have-seen counters */
    private function counters(Crawler $crawler): array
    {
        $text = $this->firstText($crawler, '.movie-panel-info .is-size-7');
        $want = $text !== null && preg_match('/([\d,]+)\s*(?:want to watch|人想看)/iu', $text, $match) === 1 ? (int) str_replace(',', '', $match[1]) : null;
        $seen = $text !== null && preg_match('/([\d,]+)\s*(?:have seen|人(?:看過|看过))/iu', $text, $match) === 1 ? (int) str_replace(',', '', $match[1]) : null;

        return [$want, $seen];
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        $crawler->filter('.preview-images a.tile-item[href]')->each(function (Crawler $link) use (&$screenshots, $baseUrl): void {
            $href = (string) $link->attr('href');
            if ($href === '' || isset($screenshots[$href])) {
                return;
            }

            $thumb = $link->filter('img')->count() > 0 ? $link->filter('img')->first()->attr('src') : null;
            $screenshots[$href] = [
                'url' => $this->absolute($baseUrl, $href),
                'thumbnail_url' => is_string($thumb) && $thumb !== '' ? $this->absolute($baseUrl, $thumb) : null,
                'position' => count($screenshots),
            ];
        });

        return array_values($screenshots);
    }

    /** @return list<array<string, mixed>> */
    private function magnets(Crawler $crawler): array
    {
        $magnets = [];
        $crawler->filter('#magnets-content .item')->each(function (Crawler $item) use (&$magnets): void {
            $href = $this->firstAttribute($item, '.magnet-name a[href^="magnet:"]', 'href');
            if ($href === null || isset($magnets[$href])) {
                return;
            }

            $meta = $this->firstText($item, '.magnet-name .meta') ?? '';
            $size = preg_match('/[\d.,]+\s*(?:[KMGT]i?B|B)\b/i', $meta, $sizeMatch) === 1 ? $sizeMatch[0] : null;
            $magnet = MovieMeta::magnet(
                $href,
                $this->firstText($item, '.magnet-name .name'),
                $size,
                $this->firstText($item, '.date .time'),
                $this->texts($item, '.magnet-name .tags .tag'),
            );
            $files = preg_match('/(\d+)\s*file/i', $meta, $filesMatch) === 1 ? (int) $filesMatch[1] : (int) $item->attr('data-files');
            if ($files > 0) {
                $magnet['files_count'] = $files;
            }

            $magnets[$href] = $magnet;
        });

        return array_values($magnets);
    }

    private function sampleVideo(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, '#preview-video source[src], .preview-video-container video source[src]', 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, string>
     */
    private function otherRows(array $rows): array
    {
        $other = [];
        foreach ($rows as $label => $cell) {
            if (! in_array($label, self::KNOWN_ROWS, true)) {
                $text = $this->normalizeText($cell->text(''));
                if ($text !== null) {
                    $other[$label] = $text;
                }
            }
        }

        return $other;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/v/([^/]+)#', $path, $match) === 1 ? $match[1] : null;
    }
}
