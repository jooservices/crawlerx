<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Jav321\Concerns\ParsesMovieCards;
use JOOservices\CrawlerX\Adapters\Jav321\Selectors;
use JOOservices\CrawlerX\Adapters\Jav321\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesHtml;
    use ParsesMovieCards;

    /**
     * Info labels in the site's language variants (jp/ja and en) mapped to a canonical key used below.
     */
    private const LABEL_ALIASES = [
        'メーカー' => 'maker', 'Studio' => 'maker', 'Maker' => 'maker', '製造商' => 'maker', '出品商' => 'maker',
        'ジャンル' => 'genre', 'Genre' => 'genre', '類型' => 'genre',
        '品番' => 'code', 'SN' => 'code', 'Code' => 'code', '番號' => 'code',
        '配信開始日' => 'release_date', 'Release Date' => 'release_date', '發行日期' => 'release_date',
        '収録時間' => 'duration', 'Play time' => 'duration', 'Length' => 'duration', '長度' => 'duration',
        'お気に入り登録数' => 'favorites', 'Likes' => 'favorites', 'Favorites' => 'favorites', '收藏數' => 'favorites',
        '平均評価' => 'rating', 'Rating' => 'rating', '評分' => 'rating', '評價' => 'rating',
        'シリーズ' => 'series', 'Series' => 'series', '系列' => 'series',
    ];

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    protected function urlNormalizer(): UrlNormalizer
    {
        return $this->normalizer;
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Jav321', 'detail');
        $crawler = new Crawler($html, $request->url);

        $externalId = $this->movieExternalId($request->url);
        $rows = $this->infoRows($crawler, $request->url);
        $code = $rows['code']['value'] ?? $this->firstText($crawler, Selectors::DETAIL_CODE);
        $images = $this->workImages($crawler, $request->url);
        $thumbnail = $this->coverThumbnail($crawler, $request->url);
        $poster = $this->firstAttribute($crawler, Selectors::DETAIL_VIDEO, 'poster');
        $cover = $images['cover'] ?? ($poster === null ? null : $this->cleanImageUrl($poster)) ?? $thumbnail;

        $item = MovieDto::item(
            url: $this->normalizer->canonical($request->url),
            externalId: $externalId,
            title: $this->title($crawler),
            data: MovieMeta::filter([
                'code' => $code,
                'cover_url' => $cover,
                'description' => $this->description($crawler),
                'date' => MovieMeta::isoDate($rows['release_date']['value'] ?? null),
                'duration' => MovieMeta::durationMinutes($rows['duration']['value'] ?? null),
                'performers' => $this->performers($crawler, $request->url),
                'tags' => $this->genreNames($rows['genre']['links'] ?? []),
                'screenshots' => $images['screenshots'],
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $rows['maker']['value'] ?? null,
                    'maker_url' => $this->firstLink($rows['maker']['links'] ?? []),
                    'genres' => $this->genreLinks($rows['genre']['links'] ?? []),
                    MovieMeta::SERIES => $rows['series']['value'] ?? null,
                    'series_url' => $this->firstLink($rows['series']['links'] ?? []),
                    MovieMeta::FAVORITE_COUNT => $this->intValue($rows['favorites']['value'] ?? null),
                    MovieMeta::RATING => $this->rating($rows['rating']['value'] ?? null),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->firstAttribute($crawler, Selectors::DETAIL_SOURCE, 'src'),
                    'sample_video_poster_url' => $poster === null ? null : $this->cleanImageUrl($poster),
                    'cover_thumbnail_url' => $thumbnail !== $cover ? $thumbnail : null,
                    'source_fields' => $this->sourceFields($rows),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'Jav321');

        return $item;
    }

    private function title(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_TITLE)->count() === 0) {
            return null;
        }

        $html = $crawler->filter(Selectors::DETAIL_TITLE)->first()->html();

        return $this->normalizeText(strip_tags(preg_replace('#<small\b.*?</small>#is', '', $html) ?? ''));
    }

    /**
     * @return array<string, array{label: string, value: ?string, links: list<array{url: string, text: string}>}>
     */
    private function infoRows(Crawler $crawler, string $baseUrl): array
    {
        $rows = [];
        $info = $crawler->filter(Selectors::DETAIL_INFO);
        if ($info->count() === 0) {
            return $rows;
        }

        $chunks = preg_split('/<br\s*\/?>/i', $info->first()->html());
        if ($chunks === false) {
            return $rows;
        }

        foreach ($chunks as $chunk) {
            $mini = new Crawler(trim($chunk));
            if ($mini->filter('b')->count() === 0) {
                continue;
            }

            $label = $this->normalizeText($mini->filter('b')->first()->text(''));
            if ($label === null) {
                continue;
            }

            $label = rtrim($label, ':');
            $links = [];
            $mini->filter('a[href]')->each(function (Crawler $anchor) use (&$links, $baseUrl): void {
                $href = $anchor->attr('href');
                $text = $this->normalizeText($anchor->text(''));
                if (is_string($href) && trim($href) !== '' && $text !== null) {
                    $links[] = ['url' => $this->normalizer->absoluteAndCanonical($baseUrl, $href), 'text' => $text];
                }
            });

            $value = preg_replace('/^\s*<b[^>]*>.*?<\/b>\s*:?\s*/is', '', trim($chunk)) ?? '';
            $rows[self::LABEL_ALIASES[$label] ?? $label] = [
                'label' => $label,
                'value' => $this->normalizeText(preg_replace('/[\\s\\x{00A0}]+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)) ?? ''),
                'links' => $links,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{external_id: ?string, name: string, url: ?string, profile_image_url?: string}>
     */
    private function performers(Crawler $crawler, string $baseUrl): array
    {
        $photos = [];
        $crawler->filter(Selectors::PERFORMER_PHOTO_CARDS)->each(function (Crawler $card) use (&$photos, $baseUrl): void {
            $image = $this->cardImage($card, $baseUrl);
            $id = $this->starId((string) $card->attr('href'));
            if ($image !== null && $id !== null && ! isset($photos[$id])) {
                $photos[$id] = $image;
            }
        });

        $performers = [];
        $crawler->filter(Selectors::DETAIL_INFO)->first()->filter(Selectors::PERFORMER_LINKS)->each(
            function (Crawler $link) use (&$performers, $photos, $baseUrl): void {
                $id = $this->starId((string) $link->attr('href'));
                $name = $this->normalizeText($link->text(''));
                if ($id === null || $name === null || isset($performers[$id])) {
                    return;
                }

                $performer = MovieMeta::performer($name, $id, $this->normalizer->absoluteAndCanonical($baseUrl, (string) $link->attr('href')));
                if (isset($photos[$id])) {
                    $performer['profile_image_url'] = $photos[$id];
                }
                $performers[$id] = $performer;
            },
        );

        return array_values($performers);
    }

    private function starId(string $href): ?string
    {
        return preg_match('#/star/(\d+)/#', $href, $match) === 1 ? $match[1] : null;
    }

    private function coverThumbnail(Crawler $crawler, string $baseUrl): ?string
    {
        $image = $crawler->filter(Selectors::DETAIL_COVER);

        return $image->count() === 0 ? null : $this->cleanImageUrl($this->imageSource($image->first(), $baseUrl));
    }

    /**
     * The work's own image column: the large cover first, then every sample still, in page order.
     *
     * @return array{cover: ?string, screenshots: list<array{url: string, thumbnail_url: null, position: int}>}
     */
    private function workImages(Crawler $crawler, string $baseUrl): array
    {
        $urls = [];
        $crawler->filter(Selectors::DETAIL_SNAPSHOT_IMAGES)->each(function (Crawler $image) use (&$urls, $baseUrl): void {
            $url = $this->cleanImageUrl($this->imageSource($image, $baseUrl));
            if ($url !== null && ! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        });

        $cover = null;
        if ($urls !== [] && preg_match('#(?:pl|jp|pf_o1[^/]*)\.jpg$#i', $urls[0]) === 1) {
            $cover = array_shift($urls);
        }

        $screenshots = [];
        foreach ($urls as $url) {
            $screenshots[] = ['url' => $url, 'thumbnail_url' => null, 'position' => count($screenshots)];
        }

        return ['cover' => $cover, 'screenshots' => $screenshots];
    }

    private function imageSource(Crawler $image, string $baseUrl): ?string
    {
        foreach (['src', 'data-original', 'data-src', 'data-lazy-src'] as $attribute) {
            $value = $image->attr($attribute);
            if (is_string($value) && trim($value) !== '' && ! str_starts_with(trim($value), 'data:')) {
                return $this->normalizer->absoluteAndCanonical($baseUrl, trim($value));
            }
        }

        return null;
    }

    private function description(Crawler $crawler): ?string
    {
        $description = null;
        $crawler->filter(Selectors::DETAIL_DESCRIPTION)->each(function (Crawler $node) use (&$description): void {
            if ($description === null && $node->filter('video,img,ul')->count() === 0) {
                $description = $this->normalizeText($node->text(''));
            }
        });

        return $description;
    }

    /**
     * @param  list<array{url: string, text: string}>  $links
     * @return list<string>
     */
    private function genreNames(array $links): array
    {
        return array_values(array_unique(array_column($links, 'text')));
    }

    /**
     * @param  list<array{url: string, text: string}>  $links
     * @return list<array{name: string, id: ?string, url: string}>
     */
    private function genreLinks(array $links): array
    {
        $genres = [];
        foreach ($links as $link) {
            $id = null;
            if (preg_match('#/genre/(\d+)/#', $link['url'], $match) === 1) {
                $id = $match[1];
            }

            $genres[] = ['name' => $link['text'], 'id' => $id, 'url' => $link['url']];
        }

        return $genres;
    }

    /**
     * @param  list<array{url: string, text: string}>  $links
     */
    private function firstLink(array $links): ?string
    {
        return $links === [] ? null : $links[0]['url'];
    }

    private function intValue(?string $value): ?int
    {
        if ($value === null || preg_match('/\d+/', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    /**
     * @return array{score: float, scale: ?float, votes: ?int}|null
     */
    private function rating(?string $value): ?array
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return MovieMeta::rating((float) $value, 5.0);
    }

    /**
     * @param  array<string, array{label: string, value: ?string, links: list<array{url: string, text: string}>}>  $rows
     * @return array<string, string>
     */
    private function sourceFields(array $rows): array
    {
        $fields = [];
        foreach ($rows as $row) {
            if ($row['value'] !== null) {
                $fields[$row['label']] = $row['value'];
            }
        }

        return $fields;
    }
}
