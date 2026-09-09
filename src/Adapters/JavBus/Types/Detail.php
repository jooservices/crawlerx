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
            data: [
                'code' => $fields['Serial Number'] ?? $code,
                'cover_url' => $this->coverUrl($crawler, $request->url),
                'description' => null,
                'date' => $this->normalizeDate($fields['Release Date'] ?? null),
                'duration' => $this->durationMinutes($fields['Length'] ?? null),
                'performers' => $this->performers($crawler),
                'tags' => $this->tags($crawler, $fields),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => array_filter([
                    'director' => $fields['Director'] ?? null,
                    'maker' => $fields['Maker'] ?? null,
                    'label' => $fields['Label'] ?? null,
                    'series' => $fields['Series'] ?? null,
                ], fn(mixed $value): bool => is_string($value) && trim($value) !== ''),
            ],
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

            $value = $this->normalizeText(str_replace(
                $node->filter(Selectors::DETAIL_INFO_HEADER)->first()->text(''),
                '',
                $node->text(''),
            )) ?? '';

            if ($value !== '') {
                $fields[$label] = $value;
            }
        });

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function performers(Crawler $crawler): array
    {
        return $this->texts($crawler, Selectors::DETAIL_PERFORMER_LINKS);
    }

    /**
     * @param  array<string, string>  $fields
     * @return list<string>
     */
    private function tags(Crawler $crawler, array $fields): array
    {
        $tags = $this->texts($crawler, Selectors::PERFORMER_TAG_LINKS);

        if ($tags === [] && isset($fields['Genre'])) {
            $split = preg_split('/\s*,\s*/', $fields['Genre']);
            $parts = $split === false ? [] : $split;
            $tags = array_values(array_filter(array_map(
                fn(string $part): string => trim($part),
                $parts,
            ), fn(string $part): bool => $part !== ''));
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return list<array{url: string, thumbnail_url: ?string}>
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
            ];
        });

        return $screenshots;
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

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $value, $matches);

        return $matches[0] ?? trim($value);
    }

    private function durationMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*minute/i', $value, $match) === 1) {
            return max(1, (int) $match[1]);
        }

        if (preg_match('/(\d+)/', $value, $match) === 1) {
            return max(1, (int) $match[1]);
        }

        return null;
    }
}
