<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Jable\Selectors;
use JOOservices\CrawlerX\Adapters\Jable\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use JOOservices\CrawlerX\Support\JableStreamParser;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Jable', 'detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->externalId($request->url);
        $rawTitle = $this->firstText($crawler, Selectors::DETAIL_TITLE)
            ?? $this->firstAttribute($crawler, 'meta[property="og:title"]', 'content')
            ?? $externalId;
        $code = CodeNormalizer::canonical($rawTitle, $externalId, $request->url) ?? strtoupper($externalId ?? '');
        $keywords = $this->keywords($crawler);
        $stream = JableStreamParser::parse($html);
        $released = $this->firstText($crawler, '.header-right .inactive-color');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->titleWithoutCode($rawTitle, $code),
            data: MovieMeta::filter([
                'code' => $code,
                'cover_url' => $this->coverUrl($crawler, $request->url),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'date' => MovieMeta::isoDate($released),
                'performers' => $this->performers($crawler, $keywords, $request->url),
                'tags' => $this->genres($crawler, $keywords),
                'metadata' => MovieMeta::filter([
                    ...$this->metadata($crawler),
                    MovieMeta::RELEASE_DATE_RAW => $released === null ? null : trim((string) preg_replace('/^Released at\s*/i', '', $released)),
                    'quality_label' => $this->qualityLabel($crawler),
                    MovieMeta::FAVORITE_COUNT => $this->count($this->firstText($crawler, '.btn.fav .count')),
                    MovieMeta::COMMENT_COUNT => $this->count($this->firstText($crawler, '.comment-list .sub-title')),
                    'categories' => $this->texts($crawler, Selectors::DETAIL_TAG_CATEGORIES),
                ]),
                'stream' => $stream,
            ]),
        );

        $this->assertUsableDetail($item, 'Jable');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function titleWithoutCode(?string $title, string $code): ?string
    {
        if ($title === null) {
            return null;
        }

        $stripped = preg_replace('/^' . preg_quote($code, '/') . '\s*/i', '', $title);

        return is_string($stripped) && trim($stripped) !== '' ? trim($stripped) : $title;
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $cover = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'content');

        return $cover === null ? null : $this->normalizer->absolute($baseUrl, $cover);
    }

    /**
     * @return list<string>
     */
    private function keywords(Crawler $crawler): array
    {
        $raw = $this->firstAttribute($crawler, Selectors::DETAIL_KEYWORDS, 'content');
        if ($raw === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn(string $part): string => trim($part),
            explode(',', $raw),
        ), fn(string $part): bool => $part !== ''));
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function genres(Crawler $crawler, array $keywords): array
    {
        $links = array_values(array_unique(array_filter(
            [
                ...$this->texts($crawler, Selectors::DETAIL_TAG_CATEGORIES),
                ...$this->texts($crawler, Selectors::DETAIL_TAG_LINKS),
            ],
            static fn(string $tag): bool => $tag !== '',
        )));

        return $links !== [] ? $links : $keywords;
    }

    /**
     * The name is the avatar tooltip (`title`) so models with a hashed url slug are not dropped; the slug is the id.
     *
     * @param  list<string>  $keywords
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performers(Crawler $crawler, array $keywords, string $baseUrl): array
    {
        $performers = [];

        $crawler->filter(Selectors::DETAIL_MODELS)->each(function (Crawler $node) use (&$performers, $baseUrl): void {
            $href = $node->attr('href');
            $path = is_string($href) ? parse_url($href, PHP_URL_PATH) : null;
            $slug = is_string($path) ? trim(basename($path), '/') : '';
            if ($slug === '') {
                return;
            }

            $holder = $node->filter('[title], [data-original-title]');
            $tooltip = $holder->count() > 0 ? ($holder->first()->attr('data-original-title') ?? $holder->first()->attr('title')) : null;
            $name = $tooltip === null ? null : $this->normalizeText($tooltip);
            $name ??= $this->isRomanModelSlug($slug) ? $this->slugToRomanName($slug) : null;
            if ($name !== null && $name !== '') {
                $performers[$slug] ??= MovieMeta::performer($name, $slug, $this->normalizer->absolute($baseUrl, $href));
            }
        });

        if ($performers === [] && $keywords !== []) {
            $last = $keywords[array_key_last($keywords)] ?? '';
            if ($this->isCjkName($last)) {
                $performers[] = MovieMeta::performer($last);
            }
        }

        return array_values($performers);
    }

    private function qualityLabel(Crawler $crawler): ?string
    {
        $label = $this->firstText($crawler, '.header-right h6');

        return $label === null ? null : $this->normalizeText((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $label));
    }

    private function count(?string $text): ?int
    {
        return $text !== null && preg_match('/(\d[\d,. ]*)/', $text, $match) === 1 ? $this->integerStat($match[1]) : null;
    }

    /**
     * The header line is "<clock> 3 years ago <eye> 563 578" followed by the model avatars, so the age and the views
     * are read from their own spans (the avatar placeholder letter is not part of the stats).
     *
     * @return array<string, mixed>
     */
    private function metadata(Crawler $crawler): array
    {
        $spans = $crawler->filter('.video-info .info-header .header-left h6 > span.mr-3')->each(
            fn(Crawler $span): ?string => $this->normalizeText($span->text('')),
        );
        $age = $spans[0] ?? null;
        $views = $spans[1] ?? null;

        return [
            'age_label' => $age,
            MovieMeta::VIEWS => $views !== null && preg_match('/^\d[\d,\s.]*$/', $views) === 1 ? $this->integerStat($views) : null,
        ];
    }

    private function integerStat(string $value): int
    {
        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized) ? (int) $normalized : 0;
    }

    private function isRomanModelSlug(string $slug): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/i', $slug) === 1) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9-]*$/i', $slug) === 1;
    }

    private function slugToRomanName(string $slug): string
    {
        $parts = array_filter(explode('-', $slug), static fn(string $part): bool => $part !== '');

        return implode(' ', array_map(
            fn(string $part): string => mb_convert_case($part, MB_CASE_TITLE, 'UTF-8'),
            $parts,
        ));
    }

    private function isCjkName(string $value): bool
    {
        return preg_match('/[\x{3040}-\x{30ff}\x{3400}-\x{9fff}]/u', $value) === 1;
    }
}
