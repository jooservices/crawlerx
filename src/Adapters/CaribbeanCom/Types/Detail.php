<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\CaribbeanCom\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Price;
use JOOservices\CrawlerX\Adapters\CaribbeanCom\Profile;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Support\SizeParser;
use Symfony\Component\DomCrawler\Crawler;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;

/**
 * Caribbeancom movie page: the spec list (Starring, Tags, Release Date, Duration), the poster, the sample video, the
 * download options per resolution and the photo gallery.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesUrlParts;

    use ParsesHtml;

    /** Keys of the player JSON that are availability flags, kept as booleans. */
    private const PLAYER_FLAGS = [
        'is_recurring', 'sample_flash_exists', 'is_movie_expired', 'is_status_enabled', 'is_old_gallery', 'is_presale',
        'is_nukumori', 'ticketonly_flag', 'ticket_enable', 'has_gallery', 'has_zip', 'is_mp4', 'has_streaming',
    ];

    public function __construct(CrawlHttpClient $client, private readonly Profile $profile)
    {
        parent::__construct($client);
    }

    protected function siteLabel(): string
    {
        return $this->profile->label;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->pathMatch($request->url, '~/moviepages/([^/]+)/~i');
        $specs = $this->specs($crawler);
        $player = $this->playerData($crawler);
        $rawDate = isset($specs['date']) ? $this->normalizeText($specs['date']->text('')) : null;

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->firstText($crawler, $this->profile->titleSelector) ?? $externalId,
            data: MovieMeta::filter([
                'code' => $externalId,
                'description' => $this->description($crawler),
                'cover_url' => $this->poster($crawler, $request->url, $externalId),
                'date' => MovieMeta::isoDate($rawDate),
                'duration' => MovieMeta::durationMinutes(isset($specs['duration']) ? $this->normalizeText($specs['duration']->text('')) : null),
                'performers' => isset($specs['performers']) ? $this->performers($specs['performers'], $request->url) : [],
                'tags' => isset($specs['tags']) ? $this->texts($specs['tags'], 'a') : [],
                'screenshots' => $this->gallery($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::SAMPLE_VIDEO_URL => $this->sampleVideo($crawler, $request->url) ?? $this->stringValue($player, 'sample_flash_url'),
                    'sample_mobile_video_url' => $this->stringValue($player, 'sample_m_flash_url'),
                    MovieMeta::DOWNLOADS => $this->downloads($crawler, $request->url),
                    MovieMeta::RELEASE_DATE_RAW => $rawDate,
                    MovieMeta::MAKER => isset($specs['maker']) ? $this->normalizeText($specs['maker']->text('')) : null,
                    ...$this->purchase($crawler),
                    ...$this->playerExtras($player),
                    MovieMeta::FIELDS => $this->otherSpecs($specs),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, $this->profile->label);

        return $item;
    }

    /** @return array<string, Crawler> spec key (performers, date, ...) or the raw spec title when unknown => content */
    private function specs(Crawler $crawler): array
    {
        $specs = [];
        $crawler->filter($this->profile->specSelector)->each(function (Crawler $spec) use (&$specs): void {
            $title = $this->firstText($spec, '.spec-title');
            if ($title === null || $spec->filter('.spec-content')->count() === 0) {
                return;
            }

            $title = rtrim($title, ': ');
            $specs[$this->profile->specKey($title) ?? $title] ??= $spec->filter('.spec-content')->first();
        });

        return $specs;
    }

    private function description(Crawler $crawler): ?string
    {
        return $this->profile->descriptionSelector === null ? null : $this->firstText($crawler, $this->profile->descriptionSelector);
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

            $id = $this->profile->performerIdPattern !== '' && preg_match($this->profile->performerIdPattern, $href, $match) === 1 ? $match[1] : null;
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

        // The player script builds the poster as /moviepages/<id>/images/<file> (see the page's inline script).
        foreach ($this->profile->posterFiles as $file) {
            $hasPosterScript = $crawler->filter('script')->reduce(static fn(Crawler $script): bool => str_contains($script->text(''), '/images/' . $file))->count() > 0;
            if ($hasPosterScript && $externalId !== null) {
                return $this->absolute($baseUrl, '/moviepages/' . $externalId . '/images/' . $file);
            }
        }

        return null;
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
            $url = $this->firstAttribute($section, $this->profile->downloadLinkSelector, 'href');
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
            $text = array_key_exists($title, $this->profile->specLabels) ? null : $this->normalizeText($cell->text(''));
            if ($text !== null) {
                $other[$title] = $text;
            }
        }

        return $other;
    }

    /**
     * Purchase and rental price plus the resolutions on sale (sites with a purchase box only).
     *
     * @return array<string, mixed>
     */
    private function purchase(Crawler $crawler): array
    {
        if ($this->profile->purchaseSelector === null) {
            return [];
        }

        $box = $crawler->filter($this->profile->purchaseSelector);
        if ($box->count() === 0) {
            return [];
        }

        return [
            MovieMeta::PRICE => $this->sectionPrice($box, '.is-cart-download'),
            'rental_price' => $this->sectionPrice($box, '.is-cart-rental, .is-cart-streaming'),
            'available_resolutions' => $this->texts($box, '.is-resolution dd'),
        ];
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function sectionPrice(Crawler $box, string $section): ?array
    {
        $section = $box->filter($section)->first();
        if ($section->count() === 0) {
            return null;
        }

        $price = Price::parse($this->firstText($section, '.is-original-price'));

        return $price === null ? null : MovieMeta::price($price['amount'], $price['currency'], $this->firstText($section, '.purchase-type'));
    }

    /**
     * The site's numeric movie id and availability flags from the player JSON (sites that opt in only).
     *
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function playerExtras(array $player): array
    {
        if (! $this->profile->playerFlags) {
            return [];
        }

        $flags = [];
        foreach (self::PLAYER_FLAGS as $key) {
            if (isset($player[$key])) {
                $flags[$key] = $player[$key] === '1' || $player[$key] === 1 || $player[$key] === true;
            }
        }

        return ['movie_seq' => $this->stringValue($player, 'movie_seq'), 'flags' => $flags];
    }
}
