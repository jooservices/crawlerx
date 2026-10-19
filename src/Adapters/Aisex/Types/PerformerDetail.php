<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Aisex\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Aisex\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'aisex', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PROFILE_NAME);
        if ($nameNode->count() === 0) {
            throw new CrawlParseException('Aisex performer detail is missing a valid name.');
        }

        $name = $this->normalizeText($nameNode->first()->text('')) ?? '';
        $externalId = $this->externalId($request->url);
        if ($name === '') {
            throw new CrawlParseException('Aisex performer detail is missing a valid name.');
        }

        $spec = $this->specs($crawler);

        $profile = $this->profileRows($crawler);
        $stats = $this->workStats($crawler);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name,
            'name_japanese' => $name,
            'name_kana' => $this->firstText($crawler, Selectors::PROFILE_RUBY),
            'birth_date_raw' => $spec['生年月日'] ?? null,
            'zodiac_sign' => $spec['星座'] ?? null,
            'blood_type' => $spec['血液型'] ?? null,
            'height_raw' => $spec['身長'] ?? null,
            'bust_raw' => $spec['バスト'] ?? null,
            'cup_size' => $spec['カップ'] ?? null,
            'waist_raw' => $spec['ウエスト'] ?? null,
            'hip_raw' => $spec['ヒップ'] ?? null,
            'profile_image_url' => $this->profileImage($crawler, $request->url),
            'tags' => $this->texts($crawler, '.genre-tags .genre-tag'),
            'hobby' => $profile['趣味'] ?? null,
            'birthplace' => $profile['出身地'] ?? null,
            'profile_fields' => array_diff_key($profile, array_flip(['趣味', '出身地'])),
            'description' => $this->firstText($crawler, '#actress-description-content'),
            'trend_summary' => $this->firstText($crawler, '.actress-unique-summary p'),
            'work_stats' => $stats,
            'review_average' => $this->reviewAverage($this->statByPrefix($stats, 'レビュー平均')),
            'price_range' => $this->statByPrefix($stats, '掲載価格の範囲'),
            'release_range' => $stats['掲載作品の配信日'] ?? null,
            'top_makers' => $this->countList($this->statByPrefix($stats, '掲載作品のメーカー')),
            'label_count' => $this->firstInt($stats['レーベル数'] ?? null),
            'sample_video' => $this->sampleVideo($crawler),
            'fanza_url' => $this->fanzaUrl($crawler, '.dmm-link-btn'),
            'popular_works' => $this->works($crawler, '.popular-work-card', $request->url, popular: true),
            'filmography' => $this->works($crawler, 'a.work-card', $request->url, popular: false),
            'related_performers' => $this->related($crawler, $request->url),
        ], fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'aisex');

        return $item;
    }

    /** @return array<string, string> label => value of the `<strong>label:</strong> value` lines under the specs */
    private function profileRows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('.profile-info p')->each(function (Crawler $line) use (&$rows): void {
            $label = $line->filter('strong');
            if ($label->count() === 0) {
                return;
            }

            $name = rtrim((string) $this->normalizeText($label->first()->text('')), ':：');
            $value = $this->normalizeText(trim(substr($line->text(''), strlen($label->first()->text('')))));
            if ($name !== '' && $value !== null && $value !== '') {
                $rows[$name] ??= $value;
            }
        });

        return $rows;
    }

    /** @return array<string, string> the "掲載作品データ" definition list */
    private function workStats(Crawler $crawler): array
    {
        $stats = [];
        $crawler->filter('.actress-work-data dl dt')->each(function (Crawler $term) use (&$stats): void {
            $label = $this->normalizeText($term->text(''));
            $next = $term->nextAll()->filter('dd')->first();
            $value = $next->count() > 0 ? $this->normalizeText($next->text('')) : null;
            if ($label !== null && $value !== null) {
                $stats[$label] ??= $value;
            }
        });

        return $stats;
    }

    /** @param array<string, string> $stats */
    private function statByPrefix(array $stats, string $prefix): ?string
    {
        foreach ($stats as $label => $value) {
            if (str_starts_with($label, $prefix)) {
                return $value;
            }
        }

        return null;
    }

    /** @return array{score: float, scale: ?float, votes: ?int}|null "3.33 / 5" */
    private function reviewAverage(?string $text): ?array
    {
        return $text !== null && preg_match('/(\d+(?:\.\d+)?)\s*\/\s*(\d+)/', $text, $match) === 1
            ? MovieMeta::rating((float) $match[1], (float) $match[2])
            : null;
    }

    /** @return list<array{name: string, count: int}> "SODクリエイト：5件 ／ ワンズファクトリー：1件" */
    private function countList(?string $text): array
    {
        $items = [];
        $parts = preg_split('/\s*[／\/]\s*/u', (string) $text);
        foreach ($parts === false ? [] : $parts as $part) {
            if (preg_match('/^(.+?)[：:]\s*(\d+)/u', $part, $match) === 1) {
                $items[] = ['name' => trim($match[1]), 'count' => (int) $match[2]];
            }
        }

        return $items;
    }

    private function firstInt(?string $text): ?int
    {
        return $text !== null && preg_match('/\d+/', $text, $match) === 1 ? (int) $match[0] : null;
    }

    /** @return array{url: string, content_id: ?string}|null the free sample video player (an iframe of the shop) */
    private function sampleVideo(Crawler $crawler): ?array
    {
        $frame = $crawler->filter('iframe[src*="litevideo"]');
        $src = $frame->count() > 0 ? $frame->first()->attr('src') : null;
        if (! is_string($src) || $src === '') {
            return null;
        }

        $src = (string) preg_replace('~/affi_id=[^/]*~', '', $src);

        return ['url' => $src, 'content_id' => preg_match('~/cid=([^/]+)~', $src, $match) === 1 ? $match[1] : null];
    }

    /** The affiliate link wraps the real shop url in `lurl`; return the shop url without the affiliate id. */
    private function shopUrl(?string $href): ?string
    {
        if ($href === null || $href === '') {
            return null;
        }

        $query = parse_url(html_entity_decode($href), PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);
        $target = $params['lurl'] ?? null;

        return is_string($target) && $target !== '' ? $target : null;
    }

    private function fanzaUrl(Crawler $crawler, string $selector): ?string
    {
        $link = $crawler->filter($selector);

        return $link->count() > 0 ? $this->shopUrl($link->first()->attr('href')) : null;
    }

    /**
     * Popular top 3 (with rank) or the full filmography: title, release date, price, cover and the shop content id.
     *
     * @return list<array<string, mixed>>
     */
    private function works(Crawler $crawler, string $selector, string $baseUrl, bool $popular): array
    {
        $works = [];
        $crawler->filter($selector)->each(function (Crawler $card) use (&$works, $baseUrl, $popular): void {
            $link = $popular ? $card->filter('a.popular-work-link') : $card;
            $href = $link->count() > 0 ? $link->first()->attr('href') : null;
            $url = $this->shopUrl(is_string($href) ? $href : null);
            $title = $this->firstText($card, $popular ? '.popular-work-title-text' : '.work-title');
            if ($title === null) {
                return;
            }

            $image = $card->filter('img')->count() > 0 ? $card->filter('img')->first()->attr('src') : null;
            $alt = $card->filter('img')->count() > 0 ? $this->normalizeText((string) $card->filter('img')->first()->attr('alt')) : null;
            $title = $popular && $alt !== null && $alt !== '' ? $alt : $title;
            $date = $popular ? null : MovieMeta::isoDate($this->firstText($card, '.work-meta span'));
            $price = $this->firstText($card, $popular ? '.popular-work-price' : '.work-price');
            $works[] = array_filter([
                'rank' => $popular ? $this->firstInt($this->firstText($card, '.popular-work-rank')) : null,
                'title' => $title,
                'date' => $date,
                'price' => $price === null || preg_match('/(\d[\d,]*)/', $price, $match) !== 1 ? null : MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $price),
                'image_url' => is_string($image) && $image !== '' ? $this->absolute($baseUrl, $image) : null,
                'content_id' => $url !== null && preg_match('/[?&]id=([^&]+)/', $url, $id) === 1 ? $id[1] : null,
                'url' => $url,
            ], static fn(mixed $value): bool => $value !== null);
        });

        return $works;
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> "related actresses" cards */
    private function related(Crawler $crawler, string $baseUrl): array
    {
        $related = [];
        $crawler->filter('a.actress-card[href*="/actress/"]')->each(function (Crawler $card) use (&$related, $baseUrl): void {
            $href = (string) $card->attr('href');
            $name = $this->firstText($card, 'h3');
            $id = preg_match('#/actress/(\d+)/#', $href, $match) === 1 ? $match[1] : null;
            if ($name !== null && $id !== null) {
                $related[$id] ??= MovieMeta::performer($name, $id, $this->absolute($baseUrl, $href));
            }
        });

        return array_values($related);
    }

    /**
     * @return array<string, string>
     */
    private function specs(Crawler $crawler): array
    {
        $spec = [];
        $crawler->filter(Selectors::SPEC_ITEMS)->each(function (Crawler $row) use (&$spec): void {
            $label = $this->firstText($row, Selectors::SPEC_LABEL);
            $value = $this->firstText($row, Selectors::SPEC_VALUE);
            if ($label !== null && $value !== null) {
                $spec[$label] = $value;
            }
        });

        return $spec;
    }

    private function profileImage(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PROFILE_IMAGE);
        if ($node->count() === 0) {
            return null;
        }

        $src = $node->first()->attr('src');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        return $this->absolute($baseUrl, $src);
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/actress/(\d+)/?$#', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
