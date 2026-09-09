<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Xcity\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'XCity', 'detail');

        $crawler = new Crawler($html, $request->url);
        $fields = $this->profileFields($crawler);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, '#program_detail_title') ?? $this->firstText($crawler, 'h1') ?? $externalId;
        $coverUrl = $this->coverUrl($crawler, $request->url);

        $item = MovieDto::item(
            url: $this->canonicalUrl($crawler, $request->url),
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $fields['Maker Code'] ?? $fields['XCITY Code'] ?? null,
                'cover_url' => $coverUrl,
                'description' => $this->firstText($crawler, 'p.lead'),
                'date' => $fields['Release Date'] ?? $fields['Sales Date'] ?? null,
                'duration' => $this->durationMinutes($fields['Duration'] ?? $fields['Running Time'] ?? null),
                'performers' => $this->texts($crawler, 'li.credit-links a[href*="/idol/detail/"]'),
                'tags' => $this->texts($crawler, 'a.genre'),
                'download_size_labels' => $this->downloadSizeLabels($crawler),
                'sample_video_url' => $this->sampleVideoUrl($crawler),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => [
                    'favorite_count' => $this->intValue($fields['★Favorite'] ?? null),
                    'sales_date' => $fields['Sales Date'] ?? null,
                    'maker_code' => $fields['Maker Code'] ?? null,
                    'xcity_code' => $fields['XCITY Code'] ?? null,
                    'maker_label' => $fields['Label/Maker'] ?? null,
                    'series' => $fields['Series'] ?? null,
                    'director' => $fields['Director'] ?? null,
                    'cast_links' => $this->castLinks($crawler, $request->url),
                ],
            ],
        );

        $this->assertUsableDetail($item, 'XCity');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function profileFields(Crawler $crawler): array
    {
        $fields = [];

        $crawler->filter('ul.profileCL > li, ul.profile > li')->each(function (Crawler $node) use (&$fields): void {
            $labelNode = $node->filter('.koumoku');
            if ($labelNode->count() === 0) {
                return;
            }

            $label = $this->cleanText($labelNode->first()->text(''));
            if ($label === '') {
                return;
            }

            $value = $this->cleanText(str_replace($label, '', $node->text('')));
            $fields[$label] = $value;
        });

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function texts(Crawler $crawler, string $selector): array
    {
        $values = [];
        $crawler->filter($selector)->each(function (Crawler $node) use (&$values): void {
            $text = $this->cleanText($node->text(''));
            if ($text !== '') {
                $values[$text] = true;
            }
        });

        return array_keys($values);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function castLinks(Crawler $crawler, string $baseUrl): array
    {
        $links = [];
        $crawler->filter('li.credit-links a[href*="/idol/detail/"]')->each(function (Crawler $node) use (&$links, $baseUrl): void {
            $href = $node->attr('href');
            $name = $this->cleanText($node->text(''));
            if (! is_string($href) || trim($href) === '' || $name === '') {
                return;
            }

            $links[] = ['name' => $name, 'url' => $this->normalizer->absolute($baseUrl, $href)];
        });

        return $links;
    }

    /**
     * @return list<array{url: string, thumbnail_url: string, position: int}>
     */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];

        $crawler->filter('#sample_images a[href]')->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $thumbnailUrl = $this->normalizer->absolute($baseUrl, $href);
            $fullUrl = str_replace('/scene/small/', '/scene/large/', $thumbnailUrl);
            $screenshots[$fullUrl] = [
                'url' => $fullUrl,
                'thumbnail_url' => $thumbnailUrl,
                'position' => count($screenshots) + 1,
            ];
        });

        return array_values($screenshots);
    }

    /**
     * @return list<string>
     */
    private function downloadSizeLabels(Crawler $crawler): array
    {
        $labels = [];
        $crawler->filter('.dl-filesize-label')->each(function (Crawler $node) use (&$labels): void {
            $label = trim($this->cleanText($node->text('')), '() ');
            if ($label !== '') {
                $labels[] = $label;
            }
        });

        return $labels;
    }

    private function sampleVideoUrl(Crawler $crawler): ?string
    {
        $node = $crawler->filter('form.sample-video-btn button[name="src"]');
        if ($node->count() === 0) {
            return null;
        }

        $value = $node->first()->attr('value');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function coverUrl(Crawler $crawler, string $baseUrl): ?string
    {
        foreach (['p.tn a[rel="lightbox"]' => 'href', 'meta[property="og:image"]' => 'content', 'img.packageThumb' => 'src'] as $selector => $attribute) {
            $node = $crawler->filter($selector);
            if ($node->count() === 0) {
                continue;
            }

            $value = $node->first()->attr($attribute);
            if (is_string($value) && trim($value) !== '') {
                return $this->normalizer->absolute($baseUrl, $value);
            }
        }

        return null;
    }

    private function canonicalUrl(Crawler $crawler, string $fallback): string
    {
        $node = $crawler->filter('link[rel="canonical"]');
        if ($node->count() === 0) {
            return $fallback;
        }

        $href = $node->first()->attr('href');

        return is_string($href) && trim($href) !== '' ? trim($href) : $fallback;
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $id = $params['id'] ?? null;
            if (is_string($id) && trim($id) !== '') {
                return trim($id);
            }
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $text = $this->cleanText($node->first()->text(''));

        return $text !== '' ? $text : null;
    }

    private function durationMinutes(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return preg_match('/(\d{1,4})\s*min/i', $value, $match) === 1 ? (int) $match[1] : null;
    }

    private function intValue(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return preg_match('/\d+/', $value, $match) === 1 ? (int) $match[0] : null;
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
