<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avfan\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Avfan\Selectors;
use JOOservices\CrawlerX\Adapters\Avfan\UrlNormalizer;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
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
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Avfan', 'detail');
        $crawler = new Crawler($html, $request->url);
        $fields = $this->fields($crawler);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE) ?? $externalId;
        $code = $this->dataNumber($crawler) ?? $this->numberField($fields['Number'] ?? null) ?? CodeNormalizer::canonical($title, $externalId, $request->url);
        $coverUrl = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $code,
                'cover_url' => $coverUrl === null ? null : $this->normalizer->absolute($request->url, $coverUrl),
                'description' => $this->description($crawler),
                'date' => $this->date($fields['Released Date'] ?? null),
                'duration' => $this->durationMinutes($fields['Duration'] ?? null),
                'director' => $this->firstText($crawler, Selectors::DETAIL_DIRECTOR),
                'maker' => $this->firstText($crawler, Selectors::DETAIL_MAKER),
                'publisher' => $this->firstText($crawler, Selectors::DETAIL_PUBLISHER),
                'performers' => $this->texts($crawler, Selectors::DETAIL_CASTS),
                'tags' => $this->texts($crawler, Selectors::DETAIL_TAGS),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'downloads' => $this->downloads($crawler),
                'metadata' => [
                    'rating' => $this->rating($fields['Rating'] ?? null),
                    'raw_fields' => $fields,
                ],
            ],
        );

        $this->assertUsableDetail($item, 'Avfan');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function fields(Crawler $crawler): array
    {
        $fields = [];
        $crawler->filter('ul.flex.flex-col > li')->each(function (Crawler $node) use (&$fields): void {
            $strong = $node->filter('strong');
            if ($strong->count() === 0) {
                return;
            }

            $label = trim($this->cleanText($strong->first()->text('')), ': ');
            if ($label === '') {
                return;
            }

            $value = $this->cleanText(str_replace($strong->first()->text(''), '', $node->text('')));
            $fields[$label] = trim($value, ': ');
        });

        return $fields;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#/en/movies/([^/]+)#', $path, $match) === 1 ? $match[1] : null;
    }

    private function dataNumber(Crawler $crawler): ?string
    {
        $node = $crawler->filter('[data-number]');
        if ($node->count() === 0) {
            return null;
        }

        $value = $node->first()->attr('data-number');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function numberField(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_match('/([A-Z0-9]{2,12})\s*-\s*(\d{2,6})/i', $value, $match) === 1
            ? strtoupper($match[1]) . '-' . $match[2]
            : null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = date_create_immutable(trim($value));

        return $date === false ? null : $date->format('Y-m-d');
    }

    private function durationMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_match('/(\d{1,4})\s*mins?/i', $value, $match) === 1 ? (int) $match[1] : null;
    }

    private function description(Crawler $crawler): ?string
    {
        $description = $this->firstAttribute($crawler, 'meta[name="description"]', 'content');

        return $description !== null && ! str_contains($description, 'Focused on collecting') ? $description : null;
    }

    /**
     * @return list<array{url: string, thumbnail_url: ?string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        $crawler->filter(Selectors::DETAIL_SCREENSHOTS)->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $thumbnail = null;
            $image = $node->filter('img');
            if ($image->count() > 0) {
                $candidate = $image->first()->attr('data-src') ?? $image->first()->attr('src');
                $thumbnail = is_string($candidate) && trim($candidate) !== '' ? $this->normalizer->absolute($baseUrl, $candidate) : null;
            }

            $url = $this->normalizer->absolute($baseUrl, $href);
            $screenshots[$url] = [
                'url' => $url,
                'thumbnail_url' => $thumbnail,
                'position' => count($screenshots) + 1,
            ];
        });

        return array_values($screenshots);
    }

    /**
     * @return list<array{type: string, url: string, quality: ?string, size_label: ?string, size_bytes: ?int, metadata: array<string, mixed>}>
     */
    private function downloads(Crawler $crawler): array
    {
        $downloads = [];
        $crawler->filter(Selectors::DETAIL_MAGNETS)->each(function (Crawler $node) use (&$downloads): void {
            $link = $node->filter('a[href^="magnet:"]');
            if ($link->count() === 0) {
                return;
            }

            $url = $link->first()->attr('href');
            if (! is_string($url) || trim($url) === '') {
                return;
            }

            $flags = $this->texts($node, '.sub-tag');
            $details = $this->magnetDetails($node);

            $downloads[] = [
                'type' => 'magnet',
                'url' => html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5),
                'quality' => $flags === [] ? null : implode(', ', $flags),
                'size_label' => $details['size_label'],
                'size_bytes' => SizeParser::bytes($details['size_label']),
                'metadata' => [
                    'filename' => $this->firstText($node, '.break-all'),
                    'date' => $details['date'],
                    'files_count' => $details['files_count'],
                    'flags' => $flags,
                ],
            ];
        });

        return $downloads;
    }

    /**
     * @return array{date: ?string, files_count: ?int, size_label: ?string}
     */
    private function magnetDetails(Crawler $node): array
    {
        $text = $this->firstText($node, '.text-sm') ?? '';
        $date = preg_match('/(\d{2}\/\d{2}\/\d{4})/', $text, $dateMatch) === 1 ? $this->date($dateMatch[1]) : null;
        $filesCount = preg_match('/(\d+)\s*file\(s\)/i', $text, $filesMatch) === 1 ? (int) $filesMatch[1] : null;
        $sizeLabel = preg_match('/(\d+(?:[.,]\d+)?)\s*(B|KB|KIB|MB|MIB|GB|GIB|TB|TIB)\b/i', $text, $sizeMatch) === 1
            ? str_replace(',', '.', $sizeMatch[1]) . ' ' . strtoupper($sizeMatch[2])
            : null;

        return [
            'date' => $date,
            'files_count' => $filesCount,
            'size_label' => $sizeLabel,
        ];
    }

    private function rating(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/(\d+(?:\.\d+)?)\s*star/i', $value, $match) === 1 ? (float) $match[1] : null;
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
