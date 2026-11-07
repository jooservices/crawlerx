<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Plum\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Plum\Cast;
use JOOservices\CrawlerX\Adapters\Plum\Selectors;
use JOOservices\CrawlerX\Adapters\Plum\Site;
use JOOservices\CrawlerX\Adapters\Plum\UrlNormalizer;
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

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Plum', 'detail');
        $crawler = new Crawler($html, $request->url);
        $rows = $this->rows($crawler);
        $slug = basename(rtrim((string) parse_url($request->url, PHP_URL_PATH), '/'));
        $code = $this->text($rows, '品番');
        $series = $this->links($rows, 'シリーズ', $request->url)[0] ?? null;
        $categories = $this->links($rows, 'カテゴリ', $request->url);
        $cast = $this->text($rows, '出演女優');
        $month = $this->links($rows, '発売日', $request->url)[0] ?? null;

        $item = MovieDto::item(
            url: $request->url,
            externalId: $slug,
            title: $this->firstText($crawler, Selectors::DETAIL_TITLE) ?? $code,
            data: [
                'code' => $code,
                'cover_url' => $this->absolute($crawler, Selectors::DETAIL_COVER, 'href', $request->url),
                'description' => $this->description($crawler),
                'date' => MovieMeta::isoDate($this->text($rows, '発売日')),
                'duration' => MovieMeta::durationMinutes($this->text($rows, '収録時間')),
                'performers' => Cast::performers($cast),
                'tags' => array_column($categories, 'name'),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $this->text($rows, 'メーカー') ?? Site::MAKER,
                    MovieMeta::LABEL => $this->text($rows, 'レーベル'),
                    MovieMeta::SERIES => $series['name'] ?? null,
                    MovieMeta::DIRECTOR => $this->text($rows, '監督'),
                    MovieMeta::PRICE => $this->price($this->text($rows, '価格')),
                    MovieMeta::SAMPLE_VIDEO_URL => $this->absolute($crawler, Selectors::DETAIL_VIDEO_SOURCE, 'src', $request->url),
                    'series_url' => $series['url'] ?? null,
                    'series_id' => $series['external_id'] ?? null,
                    'category_links' => $categories,
                    'release_month_url' => $month['url'] ?? null,
                    'price_with_tax' => $this->priceWithTax($this->text($rows, '価格')),
                    'review_number' => $this->text($rows, '審査番号'),
                    'gtin13' => $this->firstAttribute($crawler, Selectors::DETAIL_GTIN, 'content'),
                    'sample_video_poster_url' => $this->absolute($crawler, Selectors::DETAIL_VIDEO, 'poster', $request->url),
                    'purchase_links' => $this->purchaseLinks($crawler, $request->url),
                    'wp_post_id' => $this->postId($crawler),
                    'source_fields' => array_map(static fn(array $row): string => $row['text'], $rows),
                ]),
            ],
        );

        $this->assertUsableMovieDetail($item, 'Plum');

        return $item;
    }

    /** @return array<string, array{text: string, node: Crawler}> */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        foreach ($crawler->filter(Selectors::DETAIL_ROWS) as $element) {
            $row = new Crawler($element);
            $label = $this->firstText($row, Selectors::DETAIL_ROW_LABEL);
            $value = $row->filter(Selectors::DETAIL_ROW_VALUE)->last();
            $text = $this->clean($value->text(''));
            if ($label !== null && $text !== null) {
                $rows[$label] = ['text' => $text, 'node' => $value];
            }
        }

        return $rows;
    }

    private function clean(string $text): ?string
    {
        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($text)));

        return $text === '' ? null : $text;
    }

    /** @param array<string, array{text: string, node: Crawler}> $rows */
    private function text(array $rows, string $label): ?string
    {
        return $rows[$label]['text'] ?? null;
    }

    /**
     * @param array<string, array{text: string, node: Crawler}> $rows
     * @return list<array{name: string, url: string, external_id: string}>
     */
    private function links(array $rows, string $label, string $pageUrl): array
    {
        if (! isset($rows[$label])) {
            return [];
        }

        $links = [];
        foreach ($rows[$label]['node']->filter(Selectors::ROW_LINKS) as $element) {
            $link = new Crawler($element);
            $url = $this->normalizer->absolute($pageUrl, $link->attr('href') ?? '');
            $name = $this->normalizeText($link->text(''));
            if ($name !== null) {
                $links[] = ['name' => $name, 'url' => $url, 'external_id' => basename(rtrim((string) parse_url($url, PHP_URL_PATH), '/'))];
            }
        }

        return $links;
    }

    private function description(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DESCRIPTION)->count() === 0) {
            return null;
        }

        $html = preg_replace('#<br\s*/?>\s*#i', "\n", $crawler->filter(Selectors::DETAIL_DESCRIPTION)->first()->html('')) ?? '';
        $text = html_entity_decode(strip_tags($html));
        $text = preg_replace(["/[ \t]+\n/", "/\n{3,}/"], ["\n", "\n\n"], $text) ?? $text;

        return trim($text) === '' ? null : trim($text);
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    private function price(?string $text): ?array
    {
        if ($text === null || preg_match('/[¥￥]\s*([\d,]+)/u', $text, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), Site::CURRENCY, $text);
    }

    private function priceWithTax(?string $text): ?int
    {
        return $text !== null && preg_match('/税込\s*[¥￥]\s*([\d,]+)/u', $text, $match) === 1
            ? (int) str_replace(',', '', $match[1])
            : null;
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function screenshots(Crawler $crawler, string $pageUrl): array
    {
        $shots = [];
        foreach ($crawler->filter(Selectors::DETAIL_GALLERY) as $element) {
            $link = new Crawler($element);
            $thumb = $this->firstAttribute($link, 'img', 'src');
            $shots[] = [
                'url' => $this->normalizer->absolute($pageUrl, $link->attr('href') ?? ''),
                'thumbnail_url' => $thumb === null ? null : $this->normalizer->absolute($pageUrl, $thumb),
                'position' => count($shots) + 1,
            ];
        }

        return $shots;
    }

    /** @return list<array{store: string, url: string, price_label: ?string}> */
    private function purchaseLinks(Crawler $crawler, string $pageUrl): array
    {
        $links = [];
        foreach ($crawler->filter(Selectors::DETAIL_BUY_LINKS) as $element) {
            $link = new Crawler($element);
            $url = $this->normalizer->absolute($pageUrl, $link->attr('href') ?? '');
            $icon = $this->firstAttribute($link, 'img', 'src');
            $links[] = [
                'store' => $icon === null ? (string) parse_url($url, PHP_URL_HOST) : pathinfo($icon, PATHINFO_FILENAME),
                'url' => $url,
                'price_label' => $this->firstText($link, 'span'),
            ];
        }

        return $links;
    }

    private function postId(Crawler $crawler): ?int
    {
        $href = $this->firstAttribute($crawler, Selectors::DETAIL_WP_POST, 'href');

        return $href !== null && preg_match('#/ttlpost/(\d+)#', $href, $match) === 1 ? (int) $match[1] : null;
    }

    private function absolute(Crawler $crawler, string $selector, string $attribute, string $pageUrl): ?string
    {
        $value = $this->firstAttribute($crawler, $selector, $attribute);

        return $value === null ? null : $this->normalizer->absolute($pageUrl, $value);
    }
}
