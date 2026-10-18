<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom\Types;

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
 * Caribbeancom movie page: the spec list (Starring, Tags, Release Date, Duration), the poster, the sample video, the
 * download options per resolution and the photo gallery.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    private const KNOWN_SPECS = ['Starring', 'Tags', 'Release Date', 'Duration'];

    protected function siteLabel(): string
    {
        return 'Caribbeancom';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->externalId($request->url);
        $specs = $this->specs($crawler);
        $player = $this->playerData($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, 'h1[itemprop="name"], .movie-info h1') ?? $externalId,
            data: MovieMeta::filter([
                'code' => $externalId,
                'cover_url' => $this->poster($crawler, $request->url, $externalId),
                'date' => MovieMeta::isoDate(isset($specs['Release Date']) ? $this->normalizeText($specs['Release Date']->text('')) : null),
                'duration' => MovieMeta::durationMinutes(isset($specs['Duration']) ? $this->normalizeText($specs['Duration']->text('')) : null),
                'performers' => isset($specs['Starring']) ? $this->performers($specs['Starring'], $request->url) : [],
                'tags' => isset($specs['Tags']) ? $this->texts($specs['Tags'], 'a') : [],
                'screenshots' => $this->gallery($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($crawler, $request->url) ?? $this->stringValue($player, 'sample_flash_url'),
                    'sample_mobile_video_url' => $this->stringValue($player, 'sample_m_flash_url'),
                    MovieMeta::DOWNLOADS => $this->downloads($crawler, $request->url),
                    MovieMeta::RELEASE_DATE_RAW => isset($specs['Release Date']) ? $this->normalizeText($specs['Release Date']->text('')) : null,
                    MovieMeta::FIELDS => $this->otherSpecs($specs),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'Caribbeancom');

        return $item;
    }

    /** @return array<string, Crawler> spec title (without the colon) => content */
    private function specs(Crawler $crawler): array
    {
        $specs = [];
        $crawler->filter('.movie-detail__spec')->each(function (Crawler $spec) use (&$specs): void {
            $title = $this->firstText($spec, '.spec-title');
            if ($title === null || $spec->filter('.spec-content')->count() === 0) {
                return;
            }

            $title = rtrim($title, ': ');
            $specs[$title] ??= $spec->filter('.spec-content')->first();
        });

        return $specs;
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    private function performers(Crawler $cell, string $baseUrl): array
    {
        $performers = [];
        $cell->filter('a')->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $name = $this->normalizeText($link->text(''));
            $href = (string) $link->attr('href');
            if ($name === null || $href === '' || isset($performers[$href])) {
                return;
            }

            $id = preg_match('~/search_act/(\d+)/~', $href, $match) === 1 ? $match[1] : null;
            $performers[$href] = MovieMeta::performer($name, $id, $this->absolute($baseUrl, $href));
        });

        return array_values($performers);
    }

    /**
     * The page defines `var Movie = {...}` (movie id, sample video urls, membership flags) for its player script; the
     * server-rendered HTML has no <video> element, so the sample urls live only there.
     *
     * @return array<string, mixed>
     */
    private function playerData(Crawler $crawler): array
    {
        foreach ($crawler->filter('script')->each(static fn(Crawler $script): string => $script->text('')) as $code) {
            if (preg_match('/var\s+Movie\s*=\s*(\{.*?\})\s*;/s', $code, $match) === 1) {
                $decoded = json_decode($match[1], true);

                /** @var array<string, mixed> $data */
                $data = is_array($decoded) ? $decoded : [];

                return $data;
            }
        }

        return [];
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function poster(Crawler $crawler, string $baseUrl, ?string $externalId): ?string
    {
        $style = $this->firstAttribute($crawler, '.vjs-poster', 'style');
        if ($style !== null && preg_match('/url\(["\']?([^"\')]+)["\']?\)/', html_entity_decode($style), $match) === 1) {
            return $this->absolute($baseUrl, $match[1]);
        }

        $poster = $this->firstAttribute($crawler, 'video[poster]', 'poster');
        if ($poster !== null) {
            return $this->absolute($baseUrl, $poster);
        }

        // The player script builds the poster as /moviepages/<id>/images/poster_en.jpg (see the page's inline script).
        $hasPosterScript = $crawler->filter('script')->reduce(static fn(Crawler $script): bool => str_contains($script->text(''), "/images/poster_en.jpg"))->count() > 0;

        return $hasPosterScript && $externalId !== null ? $this->absolute($baseUrl, '/moviepages/' . $externalId . '/images/poster_en.jpg') : null;
    }

    private function sampleVideo(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, 'video[src]', 'src') ?? $this->firstAttribute($crawler, 'video source[src]', 'src');

        return $src === null ? null : $this->absolute($baseUrl, $src);
    }

    /** @return list<array{type: string, url: ?string, quality: ?string, size_label: ?string, size_bytes: ?int}> */
    private function downloads(Crawler $crawler, string $baseUrl): array
    {
        $downloads = [];
        $crawler->filter('.download-section')->each(function (Crawler $section) use (&$downloads, $baseUrl): void {
            $url = $this->firstAttribute($section, 'a.invisible-cta, a#dl-btn', 'href');
            $quality = $this->firstText($section, '.quality');
            $quality = $quality === null ? null : preg_replace('/^(\d+p)(?=\S)/', '$1 ', $quality);
            $size = $this->firstText($section, '.filesize');
            if ($url === null && $quality === null) {
                return;
            }

            $downloads[] = [
                'type' => 'member',
                'url' => $url === null ? null : $this->absolute($baseUrl, $url),
                'quality' => $quality,
                'size_label' => $size,
                'size_bytes' => SizeParser::bytes($size),
            ];
        });

        return $downloads;
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int, is_sample: bool}> */
    private function gallery(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter('.movie-gallery a.gallery-image-wrap[href]')->each(function (Crawler $link) use (&$images, $baseUrl): void {
            $href = (string) $link->attr('href');
            if ($href === '' || isset($images[$href])) {
                return;
            }

            $thumb = $link->filter('img')->count() > 0 ? $link->filter('img')->first()->attr('src') : null;
            $images[$href] = [
                'url' => $this->absolute($baseUrl, $href),
                'thumbnail_url' => is_string($thumb) && $thumb !== '' ? $this->absolute($baseUrl, $thumb) : null,
                'position' => count($images),
                'is_sample' => $link->attr('data-is_sample') === '1',
            ];
        });

        return array_values($images);
    }

    /**
     * @param  array<string, Crawler>  $specs
     * @return array<string, string>
     */
    private function otherSpecs(array $specs): array
    {
        $other = [];
        foreach ($specs as $title => $cell) {
            $text = in_array($title, self::KNOWN_SPECS, true) ? null : $this->normalizeText($cell->text(''));
            if ($text !== null) {
                $other[$title] = $text;
            }
        }

        return $other;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('~/moviepages/([^/]+)/~i', $path, $match) === 1 ? $match[1] : null;
    }
}
