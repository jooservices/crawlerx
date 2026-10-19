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
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    /** Info labels in the other site languages (zh, ja) and older names, mapped to the English label used below. */
    private const LABEL_ALIASES = [
        'Serial Number' => 'ID', '識別碼' => 'ID', '識別碼:' => 'ID', '品番' => 'ID',
        '發行日期' => 'Release Date', '発売日' => 'Release Date',
        '長度' => 'Length', '収録時間' => 'Length',
        '導演' => 'Director', '監督' => 'Director',
        'Maker' => 'Studio', '製作商' => 'Studio', 'メーカー' => 'Studio',
        '發行商' => 'Label', 'レーベル' => 'Label',
        '系列' => 'Series', 'シリーズ' => 'Series',
    ];

    private const KNOWN_FIELDS = ['ID', 'Release Date', 'Length', 'Director', 'Studio', 'Label', 'Series'];

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavBus', 'detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->movieExternalId($request->url);
        $fields = $this->infoFields($crawler);
        $rawTitle = $this->firstText($crawler, Selectors::DETAIL_TITLE) ?? $externalId;
        $code = CodeNormalizer::canonical($rawTitle, $externalId, $request->url) ?? strtoupper((string) $externalId);

        $item = MovieDto::item(
            url: $this->normalizer->canonical($request->url),
            externalId: $externalId,
            title: $this->titleWithoutCode($rawTitle, $code),
            data: MovieMeta::filter([
                'code' => $fields['ID'] ?? $code,
                'cover_url' => $this->coverUrl($crawler, $request->url),
                'date' => MovieMeta::isoDate($fields['Release Date'] ?? null),
                'duration' => MovieMeta::durationMinutes($fields['Length'] ?? null),
                'performers' => $this->performers($crawler, $request->url),
                'tags' => $this->tags($crawler),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $fields['Studio'] ?? null,
                    MovieMeta::LABEL => $fields['Label'] ?? null,
                    MovieMeta::SERIES => $fields['Series'] ?? null,
                    MovieMeta::DIRECTOR => $fields['Director'] ?? null,
                    MovieMeta::RELEASE_DATE_RAW => $fields['Release Date'] ?? null,
                    MovieMeta::MAGNETS => $this->magnets($crawler),
                    MovieMeta::FIELDS => array_diff_key($fields, array_flip(self::KNOWN_FIELDS)),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'JavBus');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function infoFields(Crawler $crawler): array
    {
        $fields = [];

        $crawler->filter(Selectors::DETAIL_INFO)->each(function (Crawler $node) use (&$fields): void {
            if ($node->filter(Selectors::DETAIL_INFO_HEADER)->count() === 0) {
                return;
            }

            $label = $this->normalizeText($node->filter(Selectors::DETAIL_INFO_HEADER)->first()->text('')) ?? '';
            $label = rtrim($label, ':');
            if ($label === '') {
                return;
            }
            $label = self::LABEL_ALIASES[$label] ?? $label;

            $value = $this->normalizeText(str_replace(
                $node->filter(Selectors::DETAIL_INFO_HEADER)->first()->text(''),
                '',
                $node->text(''),
            )) ?? '';

            if ($value !== '' && $value !== ':') {
                $fields[$label] = $value;
            }
        });

        return $fields;
    }

    /**
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(Crawler $crawler, string $baseUrl): array
    {
        $performers = [];
        $crawler->filter(Selectors::DETAIL_PERFORMER_LINKS)->each(function (Crawler $link) use (&$performers, $baseUrl): void {
            $href = $link->attr('href');
            $name = $this->normalizeText($link->text(''));
            if (! is_string($href) || $name === null) {
                return;
            }

            $url = $this->normalizer->absoluteAndCanonical($baseUrl, $href);
            $id = preg_match('~/star/([^/?#]+)~i', $url, $match) === 1 ? $match[1] : null;
            $performers[$url] ??= MovieMeta::performer($name, $id, $url);
        });

        return array_values($performers);
    }

    /**
     * @return list<string>
     */
    private function tags(Crawler $crawler): array
    {
        return $this->texts($crawler, '.info .genre a[href*="/genre/"]');
    }

    /**
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];

        $crawler->filter(Selectors::DETAIL_SAMPLE_LINKS)->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
            $href = $node->attr('href');
            $thumb = $node->filter('img')->count() > 0 ? $node->filter('img')->first()->attr('src') : null;

            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $screenshots[] = [
                'url' => $this->normalizer->absoluteAndCanonical($baseUrl, $href),
                'thumbnail_url' => is_string($thumb) && trim($thumb) !== ''
                    ? $this->normalizer->absoluteAndCanonical($baseUrl, $thumb)
                    : null,
                'position' => count($screenshots),
            ];
        });

        return $screenshots;
    }

    /**
     * Magnet table: name (with the HD / subtitle badges), size and share date per row.
     *
     * @return list<array<string, mixed>>
     */
    private function magnets(Crawler $crawler): array
    {
        $magnets = [];
        $crawler->filter('#magnet-table tr')->each(function (Crawler $row) use (&$magnets): void {
            $cells = $row->filter('td');
            $href = $this->firstAttribute($row, 'a[href^="magnet:"]', 'href');
            if ($cells->count() < 3 || $href === null || isset($magnets[$href])) {
                return;
            }

            $magnets[$href] = MovieMeta::magnet(
                $href,
                $this->firstText($cells->eq(0), 'a[href^="magnet:"]'),
                $this->normalizeText($cells->eq(1)->text('')),
                $this->normalizeText($cells->eq(2)->text('')),
                $this->texts($cells->eq(0), 'a.btn'),
            );
        });

        return array_values($magnets);
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');

        return $src === null ? null : $this->normalizer->absoluteAndCanonical($baseUrl, $src);
    }

    private function movieExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        if (preg_match('#/(?:en/)?([^/]+)/?$#i', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title);

        return is_string($stripped) && trim($stripped) !== '' ? trim($stripped) : $title;
    }
}
