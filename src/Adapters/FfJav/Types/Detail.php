<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\FfJav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\FfJav\Selectors;
use JOOservices\CrawlerX\Adapters\FfJav\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Support\SizeParser;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'ffjav', 'detail');

        return $this->parse($request->url, $html);
    }

    private function parse(string $url, string $html): CrawlItemResultDto
    {
        $crawler = new Crawler($html, $url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE);
        $externalId = $this->externalId($url);

        $rows = $this->rows($crawler);
        $release = $rows['発売日'] ?? null;
        $download = $this->downloadUrl($crawler);
        $button = $this->downloadLabel($crawler);

        $item = MovieDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: MovieMeta::filter([
                'cover_url' => $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src'),
                'description' => $this->description($html),
                'code' => CodeNormalizer::canonical($title, $externalId, $url),
                'tags' => $this->texts($crawler, Selectors::DETAIL_TAGS),
                'date' => MovieMeta::isoDate($release) ?? $this->date($crawler),
                'duration' => MovieMeta::durationMinutes($rows['収録時間'] ?? null),
                'performers' => $this->performers($html, $crawler, $url),
                'metadata' => MovieMeta::filter([
                    MovieMeta::DIRECTOR => $rows['監督'] ?? null,
                    MovieMeta::MAKER => $rows['メーカー'] ?? null,
                    MovieMeta::LABEL => $rows['レーベル'] ?? null,
                    MovieMeta::RELEASE_DATE_RAW => $release,
                    MovieMeta::POSTED_DATE => $this->date($crawler),
                    'download_url' => $download,
                    MovieMeta::DOWNLOADS => $download === null ? [] : [array_filter([
                        'type' => 'torrent',
                        'url' => $download,
                        'name' => $button,
                        'quality' => $this->quality($button),
                        'size_label' => $this->sizeLabel($button),
                        'size_bytes' => SizeParser::bytes($button),
                    ], static fn(mixed $value): bool => $value !== null)],
                    MovieMeta::FIELDS => array_diff_key($rows, array_flip(['発売日', '収録時間', '監督', 'メーカー', 'レーベル'])),
                ]),
            ]),
        );

        $this->assertUsableDetail($item, 'ffjav');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? trim(basename($path), '/') : null;
    }

    private function description(string $html): ?string
    {
        foreach (
            [
                '/<div class="tags">.*?<\/div>\s*(.*?)<span\s+id=/is',
                '/<div class="tags">.*?<\/div>\s*(.*?)<table>/is',
            ] as $pattern
        ) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $this->normalizeText(strip_tags($match[1]));
            }
        }

        return null;
    }

    private function date(Crawler $crawler): ?string
    {
        $text = $this->firstText($crawler, Selectors::DETAIL_DATE);
        if ($text === null) {
            return null;
        }

        $date = date_create_immutable(str_replace('May.', 'May', $text));

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * "出演者:" is a plain text list; an actress who is also a tag gets that tag page as her url.
     *
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(string $html, Crawler $crawler, string $baseUrl): array
    {
        if (preg_match('/出演者:\s*([^<]+)/u', $html, $match) !== 1) {
            return [];
        }

        $tagUrls = [];
        $crawler->filter(Selectors::DETAIL_TAGS)->each(function (Crawler $tag) use (&$tagUrls, $baseUrl): void {
            $name = $this->normalizeText($tag->text(''));
            $href = $tag->attr('href');
            if ($name !== null && is_string($href) && $href !== '') {
                $tagUrls[$name] ??= $this->normalizer->absolute($baseUrl, $href);
            }
        });

        $names = array_values(array_unique(array_filter(
            array_map(fn(string $name): string => $this->normalizeText($name) ?? '', explode(',', html_entity_decode($match[1]))),
            fn(string $name): bool => $name !== '',
        )));

        return array_map(static fn(string $name): array => MovieMeta::performer($name, null, $tagUrls[$name] ?? null), $names);
    }

    /** @return array<string, string> table label (without colon) => value, from the `td.header` / value pairs */
    private function rows(Crawler $crawler): array
    {
        $rows = [];
        $crawler->filter('td.header')->each(function (Crawler $header) use (&$rows): void {
            $label = $this->normalizeText((string) preg_replace('/[:：\\s\\x{a0}]+$/u', '', $header->text('')));
            $value = $header->nextAll()->count() > 0 ? $this->normalizeText(str_replace("\u{a0}", ' ', $header->nextAll()->first()->text(''))) : null;
            if ($label !== null && $label !== '' && $value !== null && $value !== '') {
                $rows[$label] ??= $value;
            }
        });

        return $rows;
    }

    private function downloadLabel(Crawler $crawler): ?string
    {
        return $crawler->filter(Selectors::DETAIL_DOWNLOAD)->count() > 0
            ? $this->normalizeText(str_replace("\u{a0}", ' ', $crawler->filter(Selectors::DETAIL_DOWNLOAD)->first()->text('')))
            : null;
    }

    /** "HD 5.9 GB" => "HD": the text in front of the size. */
    private function quality(?string $button): ?string
    {
        if ($button === null || preg_match('/^(.*?)\s*\d+(?:[.,]\d+)?\s*(?:KB|MB|GB|TB)/i', $button, $match) !== 1) {
            return null;
        }

        return trim($match[1]) === '' ? null : trim($match[1]);
    }

    private function sizeLabel(?string $button): ?string
    {
        return $button !== null && preg_match('/\d+(?:[.,]\d+)?\s*(?:KB|MB|GB|TB)/i', $button, $match) === 1 ? $match[0] : null;
    }

    private function downloadUrl(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DOWNLOAD)->count() === 0) {
            return null;
        }

        $href = $crawler->filter(Selectors::DETAIL_DOWNLOAD)->first()->attr('href');

        return is_string($href) && trim($href) !== '' ? trim($href) : null;
    }
}
