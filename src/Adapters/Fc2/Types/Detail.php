<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Fc2\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * FC2 Content Market product page: the JSON-LD Product (name, id, image, brand, rating, price), the header (duration,
 * seller, followers, tags, devices, sale day), the sale and stock labels, the price box, the sample images, the
 * user reviews and the description, which the page loads from a separate widget URL.
 */
final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'FC2 Content Market';
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), $this->siteLabel(), $this->contextLabel());
        $crawler = new Crawler($html, $request->url);
        $ld = $this->productData($crawler);
        $externalId = $this->externalId($request->url) ?? $this->stringOf($ld, 'productID');
        $sale = $this->saleLabels($crawler);
        $price = $this->price($crawler, $ld);
        $seller = $this->seller($crawler, $ld, $request->url);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->title($crawler, $ld, $externalId) ?? $externalId,
            data: MovieMeta::filter([
                'code' => $externalId === null ? null : 'FC2-PPV-' . $externalId,
                'cover_url' => $this->imageUrl($ld) ?? $this->firstAttribute($crawler, 'meta[property="og:image"]', 'content'),
                'description' => $this->description($crawler, $request->url, $response),
                'date' => $this->saleDay($crawler),
                'duration' => MovieMeta::durationMinutes($this->firstText($crawler, '.items_article_MainitemThumb .items_article_info')),
                'tags' => $this->texts($crawler, '.items_article_TagArea a[data-tag], .items_article_TagArea a.tag'),
                'screenshots' => $this->samples($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::SELLER => $seller['name'] ?? null,
                    'seller_url' => $seller['url'] ?? null,
                    'seller_followers' => $this->followers($crawler),
                    MovieMeta::RATING => $this->rating($ld),
                    MovieMeta::PRICE => $price['current'],
                    'list_price' => $price['list'],
                    'discount_percent' => $sale['discount_percent'],
                    'sale_end_date' => $sale['sale_end_date'],
                    'remaining' => $sale['remaining'],
                    'availability' => $this->stringOf($this->arrayOf($ld, 'offers'), 'availability'),
                    'supported_devices' => $this->texts($crawler, '.items_article_softDevice ul li'),
                    'product_id_label' => $this->labelled($crawler, 'Product ID', '商品ID'),
                    MovieMeta::RELEASE_DATE_RAW => $this->labelled($crawler, 'Sale Day', '販売日'),
                    'reviews' => $this->reviews($crawler),
                ]),
            ]),
        );

        $this->assertUsableMovieDetail($item, 'FC2 Content Market');

        return $item;
    }

    /** @return array<string, mixed> */
    private function productData(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]')->each(static fn(Crawler $script): string => $script->text('')) as $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && ($decoded['@type'] ?? null) === 'Product') {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
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

        return is_string($value) && trim($value) !== '' ? trim($value) : (is_int($value) ? (string) $value : null);
    }

    /** @param array<string, mixed> $ld */
    private function title(Crawler $crawler, array $ld, ?string $externalId): ?string
    {
        $title = $this->stringOf($ld, 'name');
        if ($title !== null) {
            return $title;
        }

        $og = $this->firstAttribute($crawler, 'meta[property="og:title"]', 'content');
        if ($og === null) {
            return null;
        }

        $og = preg_replace('/^FC2-PPV-\d+\s*/', '', $og);

        return is_string($og) ? trim(preg_replace('/\s*\|\s*FC2.*$/u', '', $og) ?? $og) : null;
    }

    /** @param array<string, mixed> $ld */
    private function imageUrl(array $ld): ?string
    {
        $image = $ld['image'] ?? null;
        $url = is_array($image) ? ($image['url'] ?? null) : $image;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $ld
     * @return array{score: float, scale: ?float, votes: ?int}|null
     */
    private function rating(array $ld): ?array
    {
        $rating = $this->arrayOf($ld, 'aggregateRating');
        $score = $rating['ratingValue'] ?? null;
        if (! is_numeric($score)) {
            return null;
        }

        $best = $rating['bestRating'] ?? null;
        $votes = $rating['reviewCount'] ?? null;

        return MovieMeta::rating((float) $score, is_numeric($best) ? (float) $best : 5.0, is_numeric($votes) ? (int) $votes : null);
    }

    /**
     * @param  array<string, mixed>  $ld
     * @return array{current: ?array{amount: ?float, currency: ?string, label: ?string}, list: ?array{amount: ?float, currency: ?string, label: ?string}}
     */
    private function price(Crawler $crawler, array $ld): array
    {
        $sales = $this->firstText($crawler, '.items_article_mylistBox .price_sales');
        $list = $this->firstText($crawler, '.items_article_mylistBox .sale_Area > p:not(.price_sales)');
        $offer = $this->arrayOf($ld, 'offers')['price'] ?? null;

        $currentAmount = $this->points($sales) ?? (is_numeric($offer) ? (float) $offer : null);
        $listAmount = $this->points($list);

        return [
            'current' => $currentAmount === null ? null : MovieMeta::price($currentAmount, 'pt', $sales ?? ($currentAmount . 'pt')),
            'list' => $listAmount === null ? null : MovieMeta::price($listAmount, 'pt', $list),
        ];
    }

    private function points(?string $text): ?float
    {
        return $text !== null && preg_match('/([\d,]+)\s*pt/i', $text, $match) === 1 ? (float) str_replace(',', '', $match[1]) : null;
    }

    /** @return array{discount_percent: ?int, sale_end_date: ?string, remaining: ?int} */
    private function saleLabels(Crawler $crawler): array
    {
        $discount = $this->firstText($crawler, '.label_limite_002 span:first-child') ?? $this->firstText($crawler, '.items_article_saleTag');
        $until = $this->firstText($crawler, '.label_limite_002 span:nth-child(2)');
        $left = $this->firstText($crawler, '.label_limite_001 span:nth-child(2)') ?? $this->firstText($crawler, '.price_limited');

        return [
            'discount_percent' => $discount !== null && preg_match('/(\d+)\s*%/', $discount, $m) === 1 ? (int) $m[1] : null,
            'sale_end_date' => MovieMeta::isoDate($until),
            'remaining' => $left !== null && preg_match('/(\d+)/', $left, $m) === 1 ? (int) $m[1] : null,
        ];
    }

    private function saleDay(Crawler $crawler): ?string
    {
        return MovieMeta::isoDate($this->labelled($crawler, 'Sale Day', '販売日'));
    }

    /** "Sale Day : 2026/08/28" style lines of the header (the label can also be the Japanese one). */
    private function labelled(Crawler $crawler, string ...$labels): ?string
    {
        $found = null;
        $pattern = implode('|', array_map(static fn(string $label): string => preg_quote($label, '/'), $labels));
        $crawler->filter('.items_article_softDevice p')->each(function (Crawler $line) use (&$found, $pattern): void {
            $text = $this->normalizeText($line->text(''));
            if ($found === null && $text !== null && preg_match('/^(?:' . $pattern . ')\s*[:：]\s*(.+)$/u', $text, $match) === 1) {
                $found = trim($match[1]);
            }
        });

        return $found;
    }

    private function followers(Crawler $crawler): ?int
    {
        $text = $this->firstText($crawler, '.items_article_headerInfo li:first-child b');

        return $text !== null && ctype_digit(str_replace(',', '', $text)) ? (int) str_replace(',', '', $text) : null;
    }

    /**
     * @param  array<string, mixed>  $ld
     * @return array{name: ?string, url: ?string}
     */
    private function seller(Crawler $crawler, array $ld, string $baseUrl): array
    {
        $name = $this->firstText($crawler, '.items_article_writer a');
        $url = $this->firstAttribute($crawler, '.items_article_writer a', 'href') ?? $this->stringOf($this->arrayOf($ld, 'brand'), 'url');

        return ['name' => $name, 'url' => $url === null ? null : $this->absolute($baseUrl, $url)];
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function samples(Crawler $crawler, string $baseUrl): array
    {
        $images = [];
        $crawler->filter('.items_article_SampleImages a[data-image-slideshow]')->each(function (Crawler $link) use (&$images, $baseUrl): void {
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

    /** @return list<array<string, mixed>> */
    private function reviews(Crawler $crawler): array
    {
        $reviews = [];
        $crawler->filter('.items_article_Review .userIdreview')->each(function (Crawler $reviewer) use (&$reviews): void {
            $row = $reviewer->ancestors()->filter('li')->first();
            if ($row->count() === 0) {
                return;
            }

            $text = null;
            $row->filter('span')->each(function (Crawler $span) use (&$text): void {
                if ($span->attr('class') === null || $span->attr('class') === '') {
                    $candidate = $this->normalizeText($span->text(''));
                    if ($candidate !== null && ! is_numeric($candidate)) {
                        $text = $candidate;
                    }
                }
            });

            // A review is three sibling <li>: headline, stars, then reviewer/date/text.
            $score = null;
            $headline = null;
            $row->previousAll()->each(function (Crawler $previous) use (&$score, &$headline): void {
                $rating = null;
                $previous->filter('[class*="items_article_Star"]')->each(function (Crawler $star) use (&$rating): void {
                    if ($rating === null && preg_match('/items_article_Star(\d)\b/', (string) $star->attr('class'), $match) === 1) {
                        $rating = (int) $match[1];
                    }
                });
                if ($rating !== null && $score === null) {
                    $score = $rating;
                } elseif ($rating === null && $headline === null && $score !== null && $previous->filter('p')->count() > 0) {
                    $headline = $this->normalizeText($previous->filter('p')->first()->text(''));
                }
            });

            $reviews[] = MovieMeta::filter([
                'reviewer' => preg_replace('/^by\s+/i', '', (string) $this->normalizeText($reviewer->text(''))),
                'rating' => $score,
                'headline' => $headline,
                'posted' => $this->firstText($row, '.userDatareview'),
                'text' => $text,
            ]);
        });

        return $reviews;
    }

    /**
     * The synopsis is not in the page: an iframe loads it from /widget/article/<id>/description, and that widget only
     * answers a request that carries the session cookie the product page just set.
     */
    private function description(Crawler $crawler, string $baseUrl, CrawlHttpResponse $page): ?string
    {
        $src = $this->firstAttribute($crawler, 'iframe[data-iframe="description"]', 'src');
        if ($src === null) {
            return null;
        }

        $cookies = [];
        foreach ($page->toPsrResponse()->getHeader('Set-Cookie') as $line) {
            $pair = trim(explode(';', $line, 2)[0]);
            if (str_contains($pair, '=')) {
                $cookies[] = $pair;
            }
        }

        try {
            $headers = ['Referer' => $baseUrl];
            if ($cookies !== []) {
                $headers['Cookie'] = implode('; ', $cookies);
            }

            $response = $this->client->get($this->absolute($baseUrl, $src), $headers);
            $psr = $response->toPsrResponse();
            if ($psr->getStatusCode() >= 400) {
                return null;
            }

            $widget = new Crawler((string) $psr->getBody());
            // Scripts, styles and the invisible honeypot paragraphs are not description text: the site hides random
            // base64 tokens with several tricks (height:0, opacity:0, display:none, zoom, font-size 0...).
            $hidden = ['height:0', 'opacity:0', 'display:none', 'visibility:hidden', 'zoom:0', 'font-size:0'];
            $selector = 'script, style, ' . implode(', ', array_merge(
                array_map(static fn(string $css): string => '[style*="' . $css . '"]', $hidden),
                array_map(static fn(string $css): string => '[style*="' . str_replace(':', ': ', $css) . '"]', $hidden),
            ));
            $widget->filter($selector)->each(static function (Crawler $node): void {
                $dom = $node->getNode(0);
                $parent = $dom?->parentNode;
                if ($dom !== null && $parent !== null) {
                    $parent->removeChild($dom);
                }
            });
            $widget->filter('[data-container] p, [data-container] span, [data-container] div')->each(static function (Crawler $node): void {
                $dom = $node->getNode(0);
                $parent = $dom?->parentNode;
                if ($dom !== null && $parent !== null && $node->children()->count() === 0 && preg_match('/^[A-Za-z0-9+\/]{20,}={0,2}$/', trim($node->text(''))) === 1) {
                    $parent->removeChild($dom);
                }
            });
            $container = $widget->filter('[data-container]');
            $text = $this->normalizeText($container->count() > 0 ? $container->first()->text('') : $widget->filter('body')->text(''));
        } catch (Throwable) {
            return null;
        }

        if ($text === null) {
            return null;
        }

        // The widget answers 200 with a notice when the description cannot be shown.
        foreach (['読み込みに失敗しました', 'Failed to load', 'preparing for translation', '翻訳の準備中'] as $notice) {
            if (str_contains($text, $notice)) {
                return null;
            }
        }

        return $text;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('~/article/(\d+)~', $path, $match) === 1 ? $match[1] : null;
    }
}
