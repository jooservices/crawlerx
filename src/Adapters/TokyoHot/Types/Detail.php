<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\TokyoHot\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tokyo-Hot product page: title, the `dl.info` list (models, play and theme/tag links, label, series, release date,
 * duration, product id), description, sample video, poster and the two image groups (Movie Digest, Photo Gallery).
 * The navigation menus repeat the same words ("Models", "Play"); only the `dl.info` list is the product's data.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const TAG_ROWS = ['Play' => 'Play', 'Theme' => 'Theme', 'Tags' => 'Tags'];

    private const KNOWN_ROWS = ['Model', 'Models', 'Play', 'Theme', 'Tags', 'Label', 'Series', 'Release Date', 'Duration', 'Product ID'];

    protected function siteLabel(): string
    {
        return 'Tokyo-Hot';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler);
        $externalId = $this->cellText($rows, 'Product ID') ?? $this->idFromUrl($request->url);
        $tagGroups = $this->tagGroups($rows);
        $label = $this->cellText($rows, 'Label');
        $series = $this->cellText($rows, 'Series');
        $release = $this->cellText($rows, 'Release Date');
        $digest = $this->images($crawler, '.vcap a[rel="cap"]', $request->url);
        $photos = $this->images($crawler, '.scap a[rel="cap"]', $request->url);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->title($crawler) ?? $externalId,
            data: MovieMeta::filter([
                'code' => $externalId,
                'cover_url' => $this->poster($crawler, $request->url),
                'description' => $this->firstText($crawler, '.sentence'),
                'date' => MovieMeta::isoDate($release),
                'duration' => MovieMeta::durationMinutes($this->cellText($rows, 'Duration')),
                'performers' => $this->performers($rows, $request->url),
                'tags' => array_values(array_unique(array_merge([], ...array_values($tagGroups)))),
                'screenshots' => $this->numbered([...$photos, ...$digest]),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $label,
                    MovieMeta::LABEL => $label,
                    MovieMeta::SERIES => $series === null || preg_match('/^-+$/', $series) === 1 ? null : $series,
                    MovieMeta::SAMPLE_VIDEO_URL => $this->attribute($crawler, 'data-mp4-src', $request->url),
                    'hls_url' => $this->attribute($crawler, 'data-hls-src', $request->url),
                    MovieMeta::RELEASE_DATE_RAW => $release,
                    'tag_groups' => $tagGroups,
                    'label_url' => $this->labelUrl($rows, $request->url),
                    'photo_gallery' => $this->numbered($photos),
                    'movie_digest' => $this->numbered($digest),
                    MovieMeta::FIELDS => $this->otherRows($rows),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'Tokyo-Hot');

        return $item;
    }

    private function idFromUrl(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return preg_match('~^product/([^/]+)$~', $path, $match) === 1 ? $match[1] : null;
    }

    private function title(Crawler $crawler): ?string
    {
        $title = $this->firstText($crawler, '#main .contents > h2');
        if ($title !== null) {
            return $title;
        }

        $og = $this->firstAttribute($crawler, 'meta[property="og:title"]', 'content');
        $og = $og === null ? null : trim((string) preg_replace('/\s*\|\s*Tokyo-Hot.*$/u', '', $og));

        return $og === null || $og === '' ? null : $og;
    }

    /** @return array<string, Crawler> dt label => dd cell of the product's `dl.info` list */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $list = $crawler->filter('dl.info')->first();
        if ($list->count() === 0) {
            return $rows;
        }

        $label = null;
        foreach ($list->children() as $node) {
            $cell = new Crawler($node);
            if ($node->nodeName === 'dt') {
                $label = $this->normalizeText($cell->text(''));

                continue;
            }
            if ($node->nodeName === 'dd' && $label !== null) {
                $rows[$label] ??= $cell;
                $label = null;
            }
        }

        return $rows;
    }

    /** @param array<string, Crawler> $rows */
    private function cellText(array $rows, string $label): ?string
    {
        $value = isset($rows[$label]) ? $this->normalizeText($rows[$label]->text('')) : null;

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, list<string>>
     */
    private function tagGroups(array $rows): array
    {
        $groups = [];
        foreach (self::TAG_ROWS as $label => $key) {
            $tags = isset($rows[$label]) ? $this->texts($rows[$label], 'a') : [];
            if ($tags !== []) {
                $groups[$key] = $tags;
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(array $rows, string $baseUrl): array
    {
        $cell = $rows['Model'] ?? $rows['Models'] ?? null;
        $performers = [];
        $cell?->filter('a[href*="/cast/"]')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || $href === '') {
                return;
            }

            $id = preg_match('~/cast/(\d+)~', $href, $match) === 1 ? $match[1] : null;
            $performers[$id ?? $name] ??= MovieMeta::performer($name, $id, $this->absolute($baseUrl, $href));
        });

        return array_values($performers);
    }

    /** @param array<string, Crawler> $rows */
    private function labelUrl(array $rows, string $baseUrl): ?string
    {
        $link = isset($rows['Label']) ? $rows['Label']->filter('a[href]') : null;

        return $link === null || $link->count() === 0 ? null : $this->absolute($baseUrl, (string) $link->first()->attr('href'));
    }

    /** The sample player's poster is the real cover; `og:image` is the site logo. */
    private function poster(Crawler $crawler, string $baseUrl): ?string
    {
        return $this->attribute($crawler, 'data-poster', $baseUrl);
    }

    private function attribute(Crawler $crawler, string $name, string $baseUrl): ?string
    {
        $value = $this->firstAttribute($crawler, '[data-sample-player]', $name);

        return $value === null || trim($value) === '' ? null : $this->url($baseUrl, $value);
    }

    private function url(string $baseUrl, string $url): string
    {
        return str_replace(' ', '%20', $this->absolute($baseUrl, trim($url)));
    }

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    private function images(Crawler $crawler, string $selector, string $baseUrl): array
    {
        $images = [];
        $crawler->filter($selector)->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = trim((string) $link->attr('href'));
            if ($href === '') {
                return;
            }

            $thumb = $this->firstAttribute($link, 'img', 'data-original');
            $thumb = $thumb !== null && $thumb !== '' ? $thumb : $this->firstAttribute($link, 'img', 'src');
            $url = $this->url($baseUrl, $href);
            $images[$url] ??= [
                'url' => $url,
                'thumbnail_url' => is_string($thumb) && $thumb !== '' && ! str_contains($thumb, '1x1.png') ? $this->url($baseUrl, $thumb) : null,
            ];
        });

        return array_values($images);
    }

    /**
     * @param  list<array{url: string, thumbnail_url: ?string}>  $images
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function numbered(array $images): array
    {
        return array_map(
            static fn(array $image, int $position): array => $image + ['position' => $position],
            $images,
            array_keys($images),
        );
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, string>
     */
    private function otherRows(array $rows): array
    {
        $other = [];
        foreach ($rows as $label => $cell) {
            $value = in_array($label, self::KNOWN_ROWS, true) ? null : $this->normalizeText($cell->text(''));
            if ($value !== null && $value !== '') {
                $other[$label] = $value;
            }
        }

        return $other;
    }
}
