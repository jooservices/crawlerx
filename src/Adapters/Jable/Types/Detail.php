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

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $this->titleWithoutCode($rawTitle, $code),
            data: [
                'code' => $code,
                'cover_url' => $this->coverUrl($crawler, $request->url),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'performers' => $this->performers($crawler, $keywords),
                'tags' => $this->genres($crawler, $keywords),
                'metadata' => $this->metadata($crawler),
                'stream' => $stream,
            ],
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
        if ($keywords !== []) {
            return $keywords;
        }

        return array_values(array_unique(array_filter(
            [
                ...$this->texts($crawler, Selectors::DETAIL_TAG_CATEGORIES),
                ...$this->texts($crawler, Selectors::DETAIL_TAG_LINKS),
            ],
            static fn(string $tag): bool => $tag !== '',
        )));
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    private function performers(Crawler $crawler, array $keywords): array
    {
        $performers = [];

        $crawler->filter(Selectors::DETAIL_MODELS)->each(function (Crawler $node) use (&$performers): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $path = parse_url($href, PHP_URL_PATH);
            if (! is_string($path)) {
                return;
            }

            $slug = trim(basename($path), '/');
            if ($slug === '' || ! $this->isRomanModelSlug($slug)) {
                return;
            }

            $performers[] = $this->slugToRomanName($slug);
        });

        if ($performers === [] && $keywords !== []) {
            $last = $keywords[array_key_last($keywords)] ?? '';
            if ($this->isCjkName($last)) {
                $performers[] = $last;
            }
        }

        return array_values(array_unique(array_filter($performers, static fn(string $name): bool => $name !== '')));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Crawler $crawler): array
    {
        $metadata = [];
        $stats = $this->firstText($crawler, Selectors::DETAIL_HEADER_STATS);
        if ($stats === null) {
            return $metadata;
        }

        $metadata['age_label'] = $stats;
        $parsed = $this->parseCounterStats($this->extractCounterText($stats), lastTokenIsLikes: false);

        if ($parsed['views'] !== null) {
            $metadata['views'] = $parsed['views'];
        }

        if ($parsed['likes'] !== null) {
            $metadata['likes'] = $parsed['likes'];
        }

        return $metadata;
    }

    private function extractCounterText(string $stats): string
    {
        if (preg_match('/ago\s+(.*)$/i', $stats, $matches) === 1) {
            return trim($matches[1]);
        }

        return $stats;
    }

    /**
     * @return array{views: ?int, likes: ?int}
     */
    private function parseCounterStats(string $text, bool $lastTokenIsLikes = true): array
    {
        $tokens = preg_split('/\s+/', trim($text));
        $tokens = $tokens === false ? [] : $tokens;
        $numeric = array_values(array_filter(
            $tokens,
            fn(string $token): bool => preg_match('/^\d[\d,]*$/', $token) === 1,
        ));

        if ($numeric === []) {
            return ['views' => null, 'likes' => null];
        }

        if (! $lastTokenIsLikes || count($numeric) === 1) {
            return [
                'views' => $this->integerStat(implode('', $numeric)),
                'likes' => null,
            ];
        }

        $likes = $this->integerStat(array_pop($numeric));
        $views = $this->integerStat(implode('', $numeric));

        return ['views' => $views, 'likes' => $likes];
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
