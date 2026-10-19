<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Missav\Selectors;
use JOOservices\CrawlerX\Adapters\Missav\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use InteractsWithMissavHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'MissAV', 'detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->externalId($request->url);
        $rows = $this->rows($crawler);
        $heading = $this->firstText($crawler, 'h1');
        $title = $heading ?? $this->firstText($crawler, Selectors::DETAIL_TITLE) ?? $externalId;
        $coverUrl = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');
        $maker = $this->linkedValue($rows, 'Maker', $request->url);
        $label = $this->linkedValue($rows, 'Label', $request->url);
        $director = $this->linkedValue($rows, 'Director', $request->url);
        $series = $this->linkedValue($rows, 'Series', $request->url);
        $originalTitle = $this->textValue($rows, 'Title');
        $known = ['Release date', 'Code', 'Title', 'Actress', 'Genre', 'Maker', 'Director', 'Label', 'Series'];

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: MovieMeta::filter([
                'code' => $this->textValue($rows, 'Code') ?? CodeNormalizer::canonical($title, $externalId, $request->url),
                'cover_url' => $coverUrl === null ? null : $this->normalizer->absolute($request->url, $coverUrl),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'date' => $this->releaseDate($crawler),
                'duration' => $this->durationMinutes($crawler),
                'performers' => $this->performers($rows, $request->url),
                'tags' => $this->valueNames($rows, 'Genre', $request->url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::MAKER => $maker['name'] ?? null,
                    MovieMeta::LABEL => $label['name'] ?? null,
                    MovieMeta::DIRECTOR => $director['name'] ?? null,
                    MovieMeta::SERIES => $series['name'] ?? null,
                    MovieMeta::ORIGINAL_TITLE => $originalTitle,
                    'maker_url' => $maker['url'] ?? null,
                    'label_url' => $label['url'] ?? null,
                    'director_url' => $director['url'] ?? null,
                    'series_url' => $series['url'] ?? null,
                    'genre_urls' => array_column($this->valueLinks($rows, 'Genre', $request->url), 'url', 'name'),
                    'stream_url' => $this->firstAttribute($crawler, 'video.player[src], video[src]', 'src'),
                    MovieMeta::FIELDS => $this->otherRows($rows, $known),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'MissAV');

        return $item;
    }

    /** @return array<string, Crawler> label (without colon) => the row's `div.text-secondary` */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('div.space-y-2 > div.text-secondary')->each(function (Crawler $row) use (&$rows): void {
            $label = $row->filter('span')->count() > 0 ? $this->normalizeText($row->filter('span')->first()->text('')) : null;
            $label = $label === null ? null : rtrim($label, ': ');
            if ($label !== null && $label !== '') {
                $rows[$label] ??= $row;
            }
        });

        return $rows;
    }

    /**
     * Text of the row's value (everything after the label span).
     *
     * @param  array<string, Crawler>  $rows
     */
    private function textValue(array $rows, string $label): ?string
    {
        if (! isset($rows[$label])) {
            return null;
        }

        $row = $rows[$label];
        $full = $this->normalizeText($row->text('')) ?? '';
        $name = $this->normalizeText($row->filter('span')->first()->text('')) ?? '';
        $value = trim(substr($full, strlen($name)));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<array{name: string, url: string}>
     */
    private function valueLinks(array $rows, string $label, string $baseUrl): array
    {
        $links = [];
        if (isset($rows[$label])) {
            $rows[$label]->filter('a[href]')->each(function (Crawler $link) use (&$links, $baseUrl): void {
                $name = $this->normalizeText($link->text(''));
                if ($name !== null) {
                    $links[$name] ??= ['name' => $name, 'url' => $this->normalizer->absolute($baseUrl, (string) $link->attr('href'))];
                }
            });
        }

        return array_values($links);
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return list<string>
     */
    private function valueNames(array $rows, string $label, string $baseUrl): array
    {
        return array_column($this->valueLinks($rows, $label, $baseUrl), 'name');
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array{name: string, url: ?string}|null
     */
    private function linkedValue(array $rows, string $label, string $baseUrl): ?array
    {
        $links = $this->valueLinks($rows, $label, $baseUrl);
        if ($links !== []) {
            return $links[0];
        }

        $text = $this->textValue($rows, $label);

        return $text === null ? null : ['name' => $text, 'url' => null];
    }

    /**
     * The actress row only: the maker, label and director links are other rows (the old selector mixed them in).
     *
     * @param  array<string, Crawler>  $rows
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(array $rows, string $baseUrl): array
    {
        $performers = [];
        foreach ($this->valueLinks($rows, 'Actress', $baseUrl) as $link) {
            $slug = basename((string) parse_url($link['url'], PHP_URL_PATH));
            $performers[] = MovieMeta::performer($link['name'], $slug === '' ? null : rawurldecode($slug), $link['url']);
        }

        return $performers;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @param  list<string>  $known
     * @return array<string, string>
     */
    private function otherRows(array $rows, array $known): array
    {
        $other = [];
        foreach (array_keys($rows) as $label) {
            if (! in_array($label, $known, true)) {
                $value = $this->textValue($rows, $label);
                if ($value !== null) {
                    $other[$label] = $value;
                }
            }
        }

        return $other;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function releaseDate(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DATE)->count() === 0) {
            return null;
        }

        $node = $crawler->filter(Selectors::DETAIL_DATE)->first();
        $value = trim($node->attr('content') ?? $node->attr('datetime') ?? $node->attr('data-release-date') ?? $node->text(''));
        if ($value === '') {
            return null;
        }

        preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $value, $matches);

        return isset($matches[0]) ? str_replace('/', '-', $matches[0]) : trim($value);
    }

    private function durationMinutes(Crawler $crawler): ?int
    {
        if ($crawler->filter(Selectors::DETAIL_DURATION)->count() === 0) {
            return null;
        }

        $node = $crawler->filter(Selectors::DETAIL_DURATION)->first();
        $value = trim($node->attr('content') ?? $node->attr('data-duration') ?? $node->text(''));
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(1, (int) round(((float) $value) / 60));
        }

        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $value, $matches) === 1) {
            return max(1, ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0));
        }

        if (preg_match('/(?:(\d+)\s*h(?:ours?)?)?\s*(?:(\d+)\s*m(?:in(?:utes?)?)?)?/i', $value, $matches) === 1 && (($matches[1] ?? '') !== '' || ($matches[2] ?? '') !== '')) {
            return max(1, ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0));
        }

        if (preg_match('/(\d{1,3})\s*min/i', $value, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return null;
    }
}
