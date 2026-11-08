<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavMenu\Selectors;
use JOOservices\CrawlerX\Adapters\JavMenu\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A work page. The labels of the info card change with the interface language, its row classes do not, so the
 * fields are read by class and, for the three plain rows (published, updated, duration), by their value.
 */
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
        $externalId = Site::workId($request->url);
        $rows = $this->rows($crawler);
        $code = $this->code($crawler);
        $jsonLd = $this->jsonLd($crawler);
        $plain = $this->plainValues($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->title($crawler, $jsonLd, $code),
            data: [
                'code' => $code,
                'cover_url' => $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'content')
                    ?? $this->firstAttribute($crawler, Selectors::DETAIL_POSTER, 'poster'),
                'date' => MovieMeta::isoDate($plain['dates'][0] ?? null),
                'duration' => $plain['duration'],
                'performers' => $this->links($crawler, Selectors::DETAIL_ACTRESSES, $request->url, true),
                'tags' => $this->texts($crawler, Selectors::DETAIL_GENRES),
                'metadata' => MovieMeta::filter(
                    $this->entity(MovieMeta::DIRECTOR, $crawler, Selectors::DETAIL_DIRECTOR, $request->url)
                    + $this->entity(MovieMeta::MAKER, $crawler, Selectors::DETAIL_MAKER, $request->url)
                    + $this->entity(MovieMeta::SERIES, $crawler, Selectors::DETAIL_SERIES, $request->url)
                    + [
                        'code_prefix' => $this->firstText($crawler, Selectors::DETAIL_CODE_PREFIX),
                        'code_prefix_url' => $this->link($crawler, Selectors::DETAIL_CODE_PREFIX, $request->url),
                        'updated_date' => MovieMeta::isoDate($plain['dates'][1] ?? null),
                        'upload_datetime' => is_string($jsonLd['uploadDate'] ?? null) ? $jsonLd['uploadDate'] : null,
                        'genres' => $this->links($crawler, Selectors::DETAIL_GENRES, $request->url, false),
                        'online_available' => $crawler->filter('.left-wrapper ' . Selectors::BADGE_ONLINE)->count() > 0,
                        'downloadable' => $crawler->filter('.left-wrapper ' . Selectors::BADGE_DOWNLOAD)->count() > 0,
                        MovieMeta::MAGNETS => $this->magnets($crawler),
                        'stream_urls' => $this->streams($crawler),
                        'alternate_urls' => $this->alternates($crawler),
                        'source_fields' => $rows,
                    ],
                ),
            ],
        );

        $this->assertUsableMovieDetail($item, Site::NAME);

        return $item;
    }

    /** @return array<string, string> label => value of every row of the info card */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_INFO_ROWS)->each(function (Crawler $row) use (&$rows): void {
            $field = $this->field($row);
            if ($field !== null) {
                $rows[$field[0]] = $field[1];
            }
        });

        return $rows;
    }

    /**
     * The values of the plain rows, the ones without a link: published date, updated date and duration. The page
     * language changes their labels, not their values.
     *
     * @return array{dates: list<string>, duration: ?int}
     */
    private function plainValues(Crawler $crawler): array
    {
        $dates = [];
        $duration = null;
        $crawler->filter(Selectors::DETAIL_INFO_ROWS)->reduce(
            static fn(Crawler $row): bool => $row->filter('a')->count() === 0,
        )->each(function (Crawler $row) use (&$dates, &$duration): void {
            $value = $this->field($row)[1] ?? '';
            if (MovieMeta::isoDate($value) !== null) {
                $dates[] = $value;
            } elseif ($duration === null && preg_match('/^(\d+)\D*$/u', $value, $match) === 1) {
                $duration = (int) $match[1];
            }
        });

        return ['dates' => $dates, 'duration' => $duration];
    }

    /** @return array{string, string}|null the label and value of an info row */
    private function field(Crawler $row): ?array
    {
        $label = $this->firstText($row, 'span');
        $text = $this->normalizeText($row->text(''));
        if ($label === null || $text === null || ! str_starts_with($text, $label) || $text === $label) {
            return null;
        }

        return [(string) preg_replace('/[\s:\x{FF1A}]+$/u', '', $label), trim(substr($text, strlen($label)))];
    }

    private function code(Crawler $crawler): ?string
    {
        $code = $this->firstText($crawler, '.left-wrapper .code');
        if ($code === null) {
            return null;
        }

        return preg_match('/(?:^|\s)(\S+-\S+)$/u', $code, $match) === 1 ? $match[1] : null;
    }

    /** @return array<string, mixed> */
    private function jsonLd(Crawler $crawler): array
    {
        foreach ($crawler->filter(Selectors::DETAIL_JSON_LD)->each(static fn(Crawler $node): string => $node->text('')) as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['@type'] ?? null) === 'VideoObject') {
                /** @var array<string, mixed> $data */
                return $data;
            }
        }

        return [];
    }

    /** @param array<string, mixed> $jsonLd */
    private function title(Crawler $crawler, array $jsonLd, ?string $code): ?string
    {
        $title = is_string($jsonLd['name'] ?? null) ? $this->normalizeText($jsonLd['name']) : null;
        $title ??= $this->firstText($crawler, Selectors::DETAIL_TITLE);

        return $title !== null && $code !== null && str_starts_with($title, $code . ' ')
            ? trim(substr($title, strlen($code)))
            : $title;
    }

    /**
     * @return array<string, string|null>
     */
    private function entity(string $key, Crawler $crawler, string $selector, string $baseUrl): array
    {
        $name = $this->firstText($crawler, $selector);
        $url = $this->link($crawler, $selector, $baseUrl);

        return [
            $key => $name,
            $key . '_id' => $url === null ? null : Site::entityId($url),
            $key . '_url' => $url,
        ];
    }

    private function link(Crawler $crawler, string $selector, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, $selector, 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }

    /**
     * @return list<array{external_id: ?string, name: string, url: ?string}>|list<array{id: ?string, name: string, url: string}>
     */
    private function links(Crawler $crawler, string $selector, string $baseUrl, bool $performers): array
    {
        $links = [];
        $crawler->filter($selector)->each(function (Crawler $node) use (&$links, $baseUrl, $performers): void {
            $name = $this->normalizeText($node->text(''));
            $href = (string) $node->attr('href');
            if ($name === null || $href === '') {
                return;
            }

            $url = $this->absolute($baseUrl, $href);
            $links[$url] ??= $performers
                ? MovieMeta::performer($name, Site::performerId($url), $url)
                : ['id' => Site::entityId($url), 'name' => $name, 'url' => $url];
        });

        return array_values($links);
    }

    /** @return list<array{name: ?string, url: string, size_label: ?string, size_bytes: ?int, date: ?string, tags: list<string>}> */
    private function magnets(Crawler $crawler): array
    {
        $magnets = [];
        $crawler->filter(Selectors::DETAIL_MAGNET_ROWS)->each(function (Crawler $row) use (&$magnets): void {
            $url = $this->firstAttribute($row, 'a[href^="magnet:"]', 'href');
            if ($url === null) {
                return;
            }

            $magnets[$url] ??= MovieMeta::magnet(
                $url,
                $this->firstText($row, 'a[href^="magnet:"] span'),
                null,
                MovieMeta::isoDate($this->firstText($row, 'td.date')),
                $this->texts($row, '.badge'),
            );
        });

        return array_values($magnets);
    }

    /** @return list<array{name: ?string, url: string}> the playback lines of the player (HLS playlists) */
    private function streams(Crawler $crawler): array
    {
        $streams = [];
        $crawler->filter(Selectors::DETAIL_STREAMS)->each(function (Crawler $line) use (&$streams): void {
            $url = trim((string) $line->attr('data-m3u8'));
            if ($url !== '') {
                $streams[$url] ??= ['name' => $this->normalizeText($line->text('')), 'url' => $url];
            }
        });

        return array_values($streams);
    }

    /** @return array<string, string> language => URL of the same page */
    private function alternates(Crawler $crawler): array
    {
        $alternates = [];
        $crawler->filter(Selectors::DETAIL_ALTERNATES)->each(static function (Crawler $link) use (&$alternates): void {
            $alternates[(string) $link->attr('hreflang')] = (string) $link->attr('href');
        });

        return array_filter($alternates, static fn(string $url, string $language): bool => $url !== '' && $language !== '', ARRAY_FILTER_USE_BOTH);
    }
}
