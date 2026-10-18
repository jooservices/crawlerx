<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavBus\Selectors;
use JOOservices\CrawlerX\Adapters\JavBus\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    /** Profile row labels (English, zh, ja) mapped to the key used below. */
    private const ROW_LABELS = [
        'd.o.b' => 'birth_date_raw', 'birthday' => 'birth_date_raw', '生日' => 'birth_date_raw', '誕生日' => 'birth_date_raw',
        'age' => 'age', '年齡' => 'age', '年齢' => 'age',
        'height' => 'height_raw', '身高' => 'height_raw', '身長' => 'height_raw',
        'cup' => 'cup', '罩杯' => 'cup', 'カップ' => 'cup',
        'bust' => 'bust', '胸圍' => 'bust', 'バスト' => 'bust',
        'waist' => 'waist', 'waistline' => 'waist', '腰圍' => 'waist', 'ウエスト' => 'waist',
        'hips' => 'hips', 'hipline' => 'hips', '臀圍' => 'hips', 'ヒップ' => 'hips',
        'hobby' => 'hobby', '愛好' => 'hobby', '趣味' => 'hobby',
        'birthplace' => 'birthplace', '出生地' => 'birthplace', '出身地' => 'birthplace',
    ];

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavBus', 'performer_detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->performerExternalId($request->url);
        [$fields, $otherRows] = $this->profileRows($crawler);
        $name = $this->firstText($crawler, '.avatar-box .photo-info span.pb10')
            ?? $this->firstText($crawler, Selectors::PERFORMER_NAME)
            ?? $this->firstAttribute($crawler, Selectors::PERFORMER_IMAGE, 'title');
        $imageUrl = $this->profileImageUrl($crawler, $request->url);

        $data = MovieMeta::filter([
            'external_id' => $externalId,
            'external_url' => $this->normalizer->canonical($request->url),
            'name' => $name,
            'profile_image_url' => $imageUrl,
            'birth_date_raw' => $fields['birth_date_raw'] ?? null,
            'height_raw' => $fields['height_raw'] ?? null,
            'size_raw' => $this->sizeRaw($fields),
            'age' => isset($fields['age']) ? (int) $fields['age'] : null,
            'cup' => $fields['cup'] ?? null,
            'bust' => $fields['bust'] ?? null,
            'waist' => $fields['waist'] ?? null,
            'hips' => $fields['hips'] ?? null,
            'hobby' => $fields['hobby'] ?? null,
            'birthplace' => $fields['birthplace'] ?? null,
            'fields' => $otherRows,
            'filmography' => $this->filmography($crawler, $request->url),
            'raw_profile' => $this->rawProfile($crawler),
        ]);

        $item = PerformerDto::item(
            url: $this->normalizer->canonical($request->url),
            externalId: $externalId,
            title: is_string($name) ? $name : '',
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'JavBus');

        return $item;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>} known rows by key, other rows by label
     */
    private function profileRows(Crawler $crawler): array
    {
        $known = [];
        $other = [];

        $crawler->filter('.avatar-box .photo-info p')->each(function (Crawler $row) use (&$known, &$other): void {
            $text = $this->normalizeText($row->text(''));
            if ($text === null || ! str_contains($text, ':')) {
                return;
            }

            [$label, $value] = array_map('trim', explode(':', $text, 2));
            if ($label === '' || $value === '') {
                return;
            }

            $key = self::ROW_LABELS[strtolower($label)] ?? null;
            if ($key !== null) {
                $known[$key] ??= $value;
            } else {
                $other[$label] = $value;
            }
        });

        return [$known, $other];
    }

    /**
     * The star page lists every movie of the performer as cards.
     *
     * @return list<array<string, mixed>>
     */
    private function filmography(Crawler $crawler, string $baseUrl): array
    {
        $movies = [];
        $crawler->filter(Selectors::MOVIE_BOXES)->each(function (Crawler $box) use (&$movies, $baseUrl): void {
            $href = $this->firstAttribute($box, 'a[href]', 'href') ?? $box->attr('href');
            if (! is_string($href) || $href === '') {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($baseUrl, $href);
            $dates = $box->filter('date');
            $cover = $this->firstAttribute($box, '.photo-frame img', 'src');
            $movies[$url] ??= MovieMeta::filter([
                'url' => $url,
                'code' => $dates->count() > 0 ? $this->normalizeText($dates->eq(0)->text('')) : null,
                'title' => $this->firstAttribute($box, '.photo-frame img', 'title'),
                'date' => $dates->count() > 1 ? MovieMeta::isoDate($dates->eq(1)->text('')) : null,
                'cover_url' => $cover === null ? null : $this->normalizer->absoluteAndCanonical($baseUrl, $cover),
                'flags' => $this->texts($box, '.item-tag button, .item-tag a'),
            ]);
        });

        return array_values($movies);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function sizeRaw(array $fields): ?string
    {
        $bust = $fields['bust'] ?? null;
        $waist = $fields['waist'] ?? null;
        $hips = $fields['hips'] ?? null;
        if ($bust === null || $waist === null || $hips === null) {
            return null;
        }

        $cup = isset($fields['cup']) ? '(' . $fields['cup'] . ')' : '';
        $number = static fn(string $value): string => preg_match('/\d+/', $value, $m) === 1 ? $m[0] : $value;

        return 'B' . $number($bust) . $cup . ' W' . $number($waist) . ' H' . $number($hips);
    }

    private function profileImageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::PERFORMER_IMAGE, 'src');

        return $src === null ? null : $this->normalizer->absoluteAndCanonical($baseUrl, $src);
    }

    private function rawProfile(Crawler $crawler): ?string
    {
        $node = $crawler->filter('.avatar-box .photo-info');
        if ($node->count() === 0) {
            return null;
        }

        return $this->normalizeText($node->first()->text(''));
    }

    private function performerExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(?:en/)?star/([^/]+)/?$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
