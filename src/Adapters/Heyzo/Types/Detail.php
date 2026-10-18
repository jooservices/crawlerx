<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Heyzo\Types;

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
 * HEYZO movie page: the schema.org Movie (title, duration, dates, rating), the movie info table (release, actresses,
 * series, review, type, sex styles, theme), sample video, chapter captures, download table and gallery. The
 * sidebar filter lists (actress letters, all categories) are not the movie's data.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const KNOWN_ROWS = ['Released', 'Actress(es)', 'Series', 'Review(s)', 'Type', 'Sex Styles', 'Theme'];

    private const TAG_ROWS = ['Type', 'Sex Styles', 'Theme'];

    protected function siteLabel(): string
    {
        return 'HEYZO';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $ld = $this->movieData($crawler);
        $externalId = $this->externalId($request->url);
        $rows = $this->rows($crawler);
        $tagGroups = $this->tagGroups($rows);
        $series = $this->cellText($rows, 'Series');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->title($crawler, $ld) ?? $externalId,
            data: MovieMeta::filter([
                'code' => $externalId === null ? null : 'HEYZO-' . $externalId,
                'cover_url' => $this->absoluteOrNull($request->url, $this->stringOf($ld, 'image')),
                'date' => MovieMeta::isoDate($this->cellText($rows, 'Released')) ?? MovieMeta::isoDate($this->stringOf($ld, 'dateCreated')),
                'duration' => MovieMeta::durationMinutes($this->stringOf($ld, 'duration')),
                'performers' => isset($rows['Actress(es)']) ? $this->performers($rows['Actress(es)'], $request->url) : [],
                'tags' => array_values(array_unique(array_merge([], ...array_values($tagGroups)))),
                'screenshots' => $this->gallery($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::SERIES => $series === null || preg_match('/^-+$/', $series) === 1 ? null : $series,
                    MovieMeta::RATING => $this->rating($ld, $this->cellText($rows, 'Review(s)')),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($crawler, $request->url),
                    MovieMeta::DOWNLOADS => $this->downloads($crawler),
                    MovieMeta::RELEASE_DATE_RAW => $this->cellText($rows, 'Released'),
                    'original_release_date' => MovieMeta::isoDate($this->stringOf($ld, 'dateCreated')),
                    'upload_date' => MovieMeta::isoDate($this->stringOf($this->arrayOf($ld, 'video'), 'uploadDate')),
                    'tag_groups' => $tagGroups,
                    'chapters' => $this->chapters($crawler, $request->url),
                    MovieMeta::FIELDS => $this->otherRows($rows),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'HEYZO');

        return $item;
    }

    /** @return array<string, mixed> */
    private function movieData(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]')->each(static fn(Crawler $script): string => $script->text('')) as $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && ($decoded['@type'] ?? null) === 'Movie') {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        return $this->scriptMovieData($crawler);
    }

    /**
     * The server-rendered page builds the schema.org block with JavaScript (`var movie_obj = {...}` and
     * `var aggregateRating = {...}`); read the same values from that script text.
     *
     * @return array<string, mixed>
     */
    private function scriptMovieData(Crawler $crawler): array
    {
        foreach ($crawler->filter('script')->each(static fn(Crawler $script): string => $script->text('')) as $code) {
            if (! str_contains($code, 'movie_obj')) {
                continue;
            }

            $data = [];
            $object = substr($code, (int) strpos($code, 'movie_obj'));
            foreach (['name', 'image', 'duration', 'dateCreated'] as $key) {
                if (preg_match('/"' . $key . '"\s*:\s*"([^"]*)"/', $object, $match) === 1) {
                    $data[$key] = $match[1];
                }
            }
            if (preg_match('/"uploadDate"\s*:\s*"([^"]*)"/', $object, $match) === 1) {
                $data['video'] = ['uploadDate' => $match[1]];
            }
            if (preg_match('/var\s+aggregateRating\s*=\s*\{(.*?)\}/s', $code, $block) === 1) {
                $rating = [];
                foreach (['ratingValue', 'bestRating', 'reviewCount'] as $key) {
                    if (preg_match('/"' . $key . '"\s*:\s*"([^"]*)"/', $block[1], $match) === 1) {
                        $rating[$key] = $match[1];
                    }
                }
                $data['aggregateRating'] = $rating;
            }

            return $data;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function arrayOf(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        /** @var array<string, mixed> $array */
        $array = is_array($value) ? $value : [];

        return $array;
    }

    /** @param array<string, mixed> $data */
    private function stringOf(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $ld */
    private function title(Crawler $crawler, array $ld): ?string
    {
        return $this->stringOf($ld, 'name')
            ?? $this->firstText($crawler, '#movie h1, .movie h1, h1');
    }

    private function absoluteOrNull(string $baseUrl, ?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return str_starts_with($url, '//') ? 'https:' . $url : $this->absolute($baseUrl, $url);
    }

    /** @return array<string, Crawler> info table label => value cell */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('table.movieInfo tr')->each(function (Crawler $row) use (&$rows): void {
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }

            $label = $this->normalizeText($cells->eq(0)->text(''));
            if ($label !== null) {
                $rows[$label] ??= $cells->eq(1);
            }
        });

        return $rows;
    }

    /** @param array<string, Crawler> $rows */
    private function cellText(array $rows, string $label): ?string
    {
        return isset($rows[$label]) ? $this->normalizeText($rows[$label]->text('')) : null;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, list<string>>
     */
    private function tagGroups(array $rows): array
    {
        $groups = [];
        foreach (self::TAG_ROWS as $label) {
            $tags = isset($rows[$label]) ? $this->texts($rows[$label], 'a') : [];
            if ($tags !== []) {
                $groups[$label] = $tags;
            }
        }

        return $groups;
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $cell, string $baseUrl): array
    {
        $performers = [];
        $cell->filter('a[href*="/listpages/actor_"]')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || $href === '') {
                return;
            }

            $id = preg_match('~actor_(\d+)_~', $href, $match) === 1 ? $match[1] : null;
            $key = $id ?? $name;
            $performers[$key] ??= MovieMeta::performer($name, $id, $this->absolute($baseUrl, explode('?', $href)[0]));
        });

        return array_values($performers);
    }

    /**
     * @param  array<string, mixed>  $ld
     * @return array{score: float, scale: ?float, votes: ?int}|null
     */
    private function rating(array $ld, ?string $tableValue): ?array
    {
        $aggregate = $this->arrayOf($ld, 'aggregateRating');
        $score = $aggregate['ratingValue'] ?? $tableValue;
        if (! is_numeric($score)) {
            return null;
        }

        $best = $aggregate['bestRating'] ?? 5;
        $votes = $aggregate['reviewCount'] ?? null;

        return MovieMeta::rating((float) $score, is_numeric($best) ? (float) $best : 5.0, is_numeric($votes) ? (int) $votes : null);
    }

    private function sampleVideo(Crawler $crawler, string $baseUrl): ?string
    {
        foreach ($crawler->filter('script')->each(static fn(Crawler $script): string => $script->text('')) as $code) {
            if (preg_match('~https?://sample\.heyzo\.com/[^"\'\s]+\.mp4~', $code, $match) === 1) {
                return $match[0];
            }
        }

        $src = $this->firstAttribute($crawler, 'video source[src], video[src]', 'src');

        return $this->absoluteOrNull($baseUrl, $src);
    }

    /**
     * Whole-movie download sizes per quality plus the bit rate and screen size of each quality.
     *
     * @return list<array<string, mixed>>
     */
    private function downloads(Crawler $crawler): array
    {
        $qualities = [];
        $crawler->filter('table.downloads.exp tr')->each(function (Crawler $row) use (&$qualities): void {
            $cells = $row->filter('td');
            $name = $cells->count() > 0 ? $this->normalizeText($cells->eq(0)->text('')) : null;
            if ($name === null || $cells->count() < 3 || $name === 'Bit Rate') {
                return;
            }

            $qualities[strtolower($name)] = ['bitrate' => $this->normalizeText($cells->eq(1)->text('')), 'resolution' => $this->normalizeText($cells->eq(2)->text(''))];
        });

        // The whole-movie row lists the sizes in the order mobile, HD Lite, FULL HD.
        $sizes = [];
        $crawler->filter('table.downloads.has-dml-slot td.download-btn')->each(function (Crawler $cell, int $index) use (&$sizes): void {
            $kind = ['Mobile', 'HD Lite', 'FULL HD'][$index] ?? null;
            $size = $this->normalizeText($cell->text(''));
            if ($kind !== null && $size !== null) {
                $sizes[$kind] = $size;
            }
        });
        if ($sizes === []) {
            $sizes = $this->scriptSizes($crawler);
        }
        $duration = $this->firstText($crawler, 'table.downloads.has-dml-slot td:first-child')
            ?? $this->stringOf($this->movieData($crawler), 'duration');

        $downloads = [];
        foreach ($sizes as $kind => $size) {
            $extra = $qualities[strtolower($kind)] ?? [];
            $downloads[] = MovieMeta::filter([
                'type' => 'purchase',
                'quality' => $kind,
                'size_label' => $size,
                'size_bytes' => SizeParser::bytes($size),
                'bitrate' => $extra['bitrate'] ?? null,
                'resolution' => $extra['resolution'] ?? null,
                'duration' => MovieMeta::durationMinutes($duration),
            ]);
        }

        return $downloads;
    }

    /**
     * The download table is built by JavaScript from `var movieSizeGB = {'mobile': '0.19GB', ...}`.
     *
     * @return array<string, string>
     */
    private function scriptSizes(Crawler $crawler): array
    {
        $names = ['mobile' => 'Mobile', 'hd-light' => 'HD Lite', 'hd' => 'FULL HD'];
        foreach ($crawler->filter('script')->each(static fn(Crawler $script): string => $script->text('')) as $code) {
            if (preg_match('/movieSizeGB\s*=\s*\{(.*?)\}/s', $code, $block) !== 1) {
                continue;
            }

            $sizes = [];
            foreach ($names as $key => $label) {
                if (preg_match("/'" . preg_quote($key, '/') . "'\\s*:\\s*'([^']+)'/", $block[1], $match) === 1) {
                    $sizes[$label] = $match[1];
                }
            }

            return $sizes;
        }

        return [];
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function gallery(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter('#section_gallery a.lightbox[href]')->each(function (Crawler $link) use (&$images, $baseUrl): void {
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

        return $images !== [] ? array_values($images) : $this->scriptGallery($crawler, $baseUrl);
    }

    /**
     * The gallery links are written by JavaScript (`dir_gallery+'001.jpg'`): rebuild them from the script text.
     *
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function scriptGallery(Crawler $crawler, string $baseUrl): array
    {
        $code = implode("\n", $crawler->filter('script')->each(static fn(Crawler $script): string => $script->text('')));
        if (str_contains($code, 'dir_gallery')) {
            $directory = null;
            if (preg_match_all('/dir_gallery\s*=\s*"([^"]*)"/', $code, $directories) > 0) {
                foreach ($directories[1] as $candidate) {
                    if ($candidate !== '' && ! str_contains($candidate, '/member/')) {
                        $directory = $candidate;
                    }
                }
            }
            if ($directory === null || preg_match_all("/dir_gallery\\+'(\\d+)\\.jpg/", $code, $numbers) === 0) {
                return [];
            }

            $images = [];
            foreach (array_values(array_unique($numbers[1])) as $position => $number) {
                $images[] = [
                    'url' => $this->absolute($baseUrl, $directory . $number . '.jpg'),
                    'thumbnail_url' => $this->absolute($baseUrl, $directory . 'thumbnail_' . $number . '.jpg'),
                    'position' => $position,
                ];
            }

            return $images;
        }

        return [];
    }

    /** @return list<array{time_seconds: ?int, label: ?string, image_url: ?string}> */
    private function chapters(Crawler $crawler, string $baseUrl): array
    {
        $chapters = [];
        $crawler->filter('.capture a.sample-capture')->each(function (Crawler $link) use (&$chapters, $baseUrl): void {
            $image = $link->filter('img')->count() > 0 ? $link->filter('img')->first()->attr('src') : null;
            $time = $link->attr('time');
            $chapters[] = [
                'time_seconds' => is_string($time) && ctype_digit($time) ? (int) $time : null,
                'label' => $this->normalizeText($link->text('')),
                'image_url' => is_string($image) && $image !== '' ? $this->absolute($baseUrl, $image) : null,
            ];
        });

        return $chapters;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, string>
     */
    private function otherRows(array $rows): array
    {
        $other = [];
        foreach ($rows as $label => $cell) {
            $text = in_array($label, self::KNOWN_ROWS, true) ? null : $this->normalizeText($cell->text(''));
            if ($text !== null) {
                $other[$label] = $text;
            }
        }

        return $other;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('~/moviepages/(\d+)/~', $path, $match) === 1 ? $match[1] : null;
    }
}
