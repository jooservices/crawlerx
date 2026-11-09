<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Mgstage\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Adapters\Mgstage\Concerns\ParsesProductData;
use JOOservices\CrawlerX\Adapters\Mgstage\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;
    use ParsesUrlParts;
    use ParsesProductData;

    /** Row labels of the product table mapped to a canonical key; unknown rows stay in source_fields only. */
    private const LABEL_KEYS = [
        '出演' => 'performers',
        'メーカー' => 'maker',
        'レーベル' => 'label',
        'シリーズ' => 'series',
        'ジャンル' => 'genre',
        '収録時間' => 'duration',
        '品番' => 'code',
        '配信開始日' => 'release_date',
        '商品発売日' => 'dvd_release',
        '対応デバイス' => 'devices',
        '評価' => 'rating',
        '監督' => 'director',
    ];

    protected function siteLabel(): string
    {
        return 'MGStage';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $rows = $this->rows($crawler, $request->url);
        $images = $this->images($crawler, $request->url);
        $sku = $this->skuFromUrl($request->url);
        $player = $this->firstAttribute($crawler, Selectors::DETAIL_SAMPLE_PLAYER, 'href');
        $playerUrl = $player === null ? null : $this->absolute($request->url, $player);
        $dvd = $rows['dvd_release']['value'] ?? null;
        $prices = $this->prices($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $sku,
            title: $this->firstText($crawler, Selectors::DETAIL_TITLE),
            data: MovieMeta::filter([
                'code' => $rows['code']['value'] ?? $sku,
                'cover_url' => $images['cover'],
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION)
                    ?? $this->firstAttribute($crawler, Selectors::DETAIL_OG_DESCRIPTION, 'content'),
                'date' => MovieMeta::isoDate($rows['release_date']['value'] ?? null),
                'duration' => MovieMeta::durationMinutes($rows['duration']['value'] ?? null),
                'performers' => $this->performers($rows['performers'] ?? null),
                'tags' => array_values(array_unique(array_column($rows['genre']['links'] ?? [], 'text'))),
                'screenshots' => $images['screenshots'],
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $rows['maker']['value'] ?? null,
                    'maker_url' => $rows['maker']['links'][0]['url'] ?? null,
                    MovieMeta::LABEL => $rows['label']['value'] ?? null,
                    'label_url' => $rows['label']['links'][0]['url'] ?? null,
                    MovieMeta::SERIES => $rows['series']['value'] ?? null,
                    'series_url' => $rows['series']['links'][0]['url'] ?? null,
                    MovieMeta::DIRECTOR => $rows['director']['value'] ?? null,
                    'genres' => array_map(static fn(array $link): array => ['name' => $link['text'], 'url' => $link['url']], $rows['genre']['links'] ?? []),
                    'devices' => $this->devices($rows['devices']['value'] ?? null),
                    'dvd_release_date' => MovieMeta::isoDate($dvd),
                    'dvd_release_note' => MovieMeta::isoDate($dvd) === null ? $dvd : null,
                    MovieMeta::RATING => $this->ratingFromText($rows['rating']['value'] ?? null),
                    MovieMeta::FAVORITE_COUNT => $this->counter($crawler, '欲しいものリスト追加数'),
                    MovieMeta::VIEWS => $this->counter($crawler, 'サンプル再生回数'),
                    'cover_thumbnail_url' => $images['thumbnail'],
                    'prices' => $prices,
                    MovieMeta::PRICE => $this->lowestPrice($prices),
                    'sale_ends_at' => $this->saleEnd($this->firstAttribute($crawler, Selectors::DETAIL_SALE_END, 'data-end-date')),
                    'badges' => $this->badges($crawler->filter(Selectors::DETAIL_BADGES)),
                    'sample_player_url' => $playerUrl,
                    MovieMeta::SAMPLE_VIDEO_URL => $playerUrl === null ? null : $this->sampleVideoUrl($playerUrl, $request->url),
                    'reviews' => $this->reviews($crawler),
                    'source_fields' => $this->sourceFields($rows),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'MGStage');

        return $item;
    }

    /**
     * @return array<string, array{label: string, value: ?string, links: list<array{url: string, text: string}>}>
     */
    private function rows(Crawler $crawler, string $pageUrl): array
    {
        $rows = [];
        $crawler->filter(Selectors::DETAIL_ROWS)->each(function (Crawler $row) use (&$rows, $pageUrl): void {
            if ($row->filter('th')->count() === 0 || $row->filter('td')->count() === 0) {
                return;
            }

            $label = $this->normalizeText((string) preg_replace('/\s*[：:]\s*$/u', '', $row->filter('th')->first()->text('')));
            if ($label === null) {
                return;
            }

            $cell = $row->filter('td')->first();
            $links = [];
            $cell->filter('a[href]')->each(function (Crawler $anchor) use (&$links, $pageUrl): void {
                $text = $this->normalizeText($anchor->text(''));
                if ($text !== null) {
                    $links[] = ['url' => $this->absolute($pageUrl, (string) $anchor->attr('href')), 'text' => $text];
                }
            });

            $rows[self::LABEL_KEYS[$label] ?? $label] = [
                'label' => $label,
                'value' => $this->normalizeText($cell->text('')),
                'links' => $links,
            ];
        });

        return $rows;
    }

    /**
     * @param  array{label: string, value: ?string, links: list<array{url: string, text: string}>}|null  $row
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(?array $row): array
    {
        if ($row === null || $row['value'] === null) {
            return [];
        }

        if ($row['links'] === []) {
            return [MovieMeta::performer($row['value'])];
        }

        return array_map(
            static fn(array $link): array => MovieMeta::performer($link['text'], null, $link['url']),
            $row['links'],
        );
    }

    /** @return list<string> */
    private function devices(?string $value): array
    {
        return $value === null ? [] : array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $device): bool => $device !== ''));
    }

    /** A "label ... 348" or "label：2,607回" counter, in a table row or a definition. */
    private function counter(Crawler $crawler, string $label): ?int
    {
        $value = null;
        $crawler->filter(Selectors::DETAIL_COUNTERS)->each(function (Crawler $node) use (&$value, $label): void {
            $text = $node->text('');
            if ($value === null && str_contains($text, $label)) {
                $value = $this->countFromText(substr($text, (int) strpos($text, $label) + strlen($label)));
            }
        });

        return $value;
    }

    /**
     * The large jacket (enlarge link), its small variant, and every sample still with its thumbnail.
     *
     * @return array{cover: ?string, thumbnail: ?string, screenshots: list<array{url: string, thumbnail_url: ?string}>}
     */
    private function images(Crawler $crawler, string $pageUrl): array
    {
        $small = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $small = $small === null ? null : $this->absolute($pageUrl, $small);
        $large = $this->firstAttribute($crawler, Selectors::DETAIL_COVER_LARGE, 'href')
            ?? $this->firstAttribute($crawler, Selectors::DETAIL_OG_IMAGE, 'content');
        $large = $large === null ? null : $this->absolute($pageUrl, $large);

        $screenshots = [];
        $crawler->filter(Selectors::DETAIL_SCREENSHOTS)->each(function (Crawler $link) use (&$screenshots, $pageUrl): void {
            $href = trim((string) $link->attr('href'));
            $thumb = $link->filter('img')->count() === 0 ? null : $link->filter('img')->first()->attr('src');
            $url = $this->absolute($pageUrl, $href);
            if ($href !== '' && ! in_array($url, array_column($screenshots, 'url'), true)) {
                $screenshots[] = [
                    'url' => $url,
                    'thumbnail_url' => is_string($thumb) && trim($thumb) !== '' ? $this->absolute($pageUrl, trim($thumb)) : null,
                ];
            }
        });

        return [
            'cover' => $large ?? $small,
            'thumbnail' => $large !== null && $small !== $large ? $small : null,
            'screenshots' => $screenshots,
        ];
    }

    /**
     * @return list<array{format: string, label: ?string, price: array{amount: ?float, currency: ?string, label: ?string}|null, original_price: array{amount: ?float, currency: ?string, label: ?string}|null, expires: ?string}>
     */
    private function prices(Crawler $crawler): array
    {
        $prices = [];
        $crawler->filter(Selectors::DETAIL_PRICE_OPTIONS)->each(function (Crawler $option) use (&$prices): void {
            $price = $this->yenFromText($this->firstText($option, '.now-price'));
            if ($price === null) {
                return;
            }

            $prices[] = [
                'format' => $this->firstAttribute($option, 'input[name="price"]', 'value') ?? '',
                'label' => $this->firstText($option, '.price-option-title'),
                'price' => $price,
                'original_price' => $this->yenFromText($this->firstText($option, '.pre-sale-price')),
                'expires' => $this->firstText($option, '.expire-value'),
            ];
        });

        return $prices;
    }

    /**
     * @param  list<array{price: array{amount: ?float, currency: ?string, label: ?string}|null}>  $prices
     * @return array{amount: ?float, currency: ?string, label: ?string}|null
     */
    private function lowestPrice(array $prices): ?array
    {
        $lowest = null;
        foreach ($prices as $entry) {
            if ($entry['price'] !== null && ($lowest === null || ($entry['price']['amount'] ?? 0) < ($lowest['amount'] ?? 0))) {
                $lowest = $entry['price'];
            }
        }

        return $lowest;
    }

    /**
     * @return list<array{title: ?string, date: ?string, author: ?string, stars: ?float, text: ?string}>
     */
    private function reviews(Crawler $crawler): array
    {
        $reviews = [];
        $crawler->filter(Selectors::DETAIL_REVIEWS)->each(function (Crawler $node) use (&$reviews): void {
            $author = $this->firstText($node, 'p.name');
            $star = $this->firstAttribute($node, 'p.review span', 'class');
            $review = [
                'title' => $this->firstText($node, 'h4'),
                'date' => MovieMeta::isoDate($this->firstText($node, 'p.date')),
                'author' => $author === null ? null : preg_replace('/さんのレビュー$/u', '', $author),
                'stars' => $star !== null && preg_match('/star_(\d)(\d)(?:_|$)/', $star, $m) === 1 ? (float) ($m[1] . '.' . $m[2]) : null,
                'text' => $this->firstText($node, 'p.text'),
            ];

            if ($review['title'] !== null || $review['text'] !== null) {
                $reviews[serialize($review)] = $review;
            }
        });

        return array_values($reviews);
    }

    /**
     * The sample player page carries the pid; its public JSON endpoint returns the (expiring) stream URL.
     * Best effort: a missing or unreadable answer leaves the field out.
     */
    private function sampleVideoUrl(string $playerUrl, string $pageUrl): ?string
    {
        $pid = $this->lastPathSegment($playerUrl);
        if ($pid === null) {
            return null;
        }

        try {
            $response = $this->client->get(
                $this->absolute($pageUrl, '/sampleplayer/sampleRespons.php?pid=' . rawurlencode($pid)),
                ['Referer' => $playerUrl],
            );
            $data = json_decode((string) $response->toPsrResponse()->getBody(), true);
        } catch (\Throwable) {
            return null;
        }

        $url = is_array($data) && is_string($data['url'] ?? null) ? html_entity_decode($data['url']) : null;

        return $url !== null && str_starts_with($url, 'http') ? $url : null;
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
