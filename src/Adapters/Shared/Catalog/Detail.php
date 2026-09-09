<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\Catalog;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly CatalogDefinition $definition,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), $this->definition->label, 'detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->definition->externalId($request->url);
        $title = $this->title($crawler) ?? $externalId;
        $fields = $this->definitionFields($crawler);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => $fields['Product ID'] ?? $fields['品番'] ?? $externalId,
                'cover_url' => $this->cover($crawler, $request->url),
                'description' => $this->meta($crawler, 'name', 'description'),
                'date' => $this->date($fields),
                'duration' => $this->duration($fields),
                'performers' => $this->textsFromSelectors($crawler, $this->definition->performerSelectors),
                'tags' => $this->textsFromSelectors($crawler, $this->definition->tagSelectors),
                'screenshots' => $this->screenshots($crawler, $request->url),
                'metadata' => $fields,
            ],
        );

        $this->assertUsableMovieDetail($item, $this->definition->label);

        return $item;
    }

    private function title(Crawler $crawler): ?string
    {
        $title = null;
        foreach ($this->definition->detailTitleSelectors as $selector) {
            $title = $this->firstText($crawler, $selector);
            if ($title !== null) {
                break;
            }
        }

        $title ??= $this->meta($crawler, 'property', 'og:title')
            ?? $this->firstText($crawler, 'h1, .pagetitle h2, #video_title, .video-title')
            ?? $this->firstText($crawler, 'title');

        if ($title === null) {
            return null;
        }

        foreach ($this->definition->titleSuffixes as $suffix) {
            if (str_ends_with($title, $suffix)) {
                $title = trim(substr($title, 0, -strlen($suffix)));
            }
        }

        return $title === '' ? null : $title;
    }

    private function cover(Crawler $crawler, string $baseUrl): ?string
    {
        $cover = $this->meta($crawler, 'property', 'og:image');
        if ($cover === null) {
            /** @var array<string, string> $selectors */
            $selectors = $this->definition->coverSelectors + [
                'video[poster]' => 'poster',
                '.video-cover img' => 'src',
                '#video_jacket_img' => 'src',
                '.movie img' => 'src',
            ];
            foreach ($selectors as $selector => $attribute) {
                $cover = $this->firstAttribute($crawler, $selector, $attribute);
                if ($cover !== null) {
                    break;
                }
            }
        }

        return $cover === null ? null : $this->absolute($baseUrl, $cover);
    }

    private function meta(Crawler $crawler, string $attribute, string $value): ?string
    {
        return $this->firstAttribute($crawler, sprintf('meta[%s="%s"]', $attribute, $value), 'content');
    }

    /** @return array<string, string> */
    private function definitionFields(Crawler $crawler): array
    {
        $fields = [];
        $crawler->filter('dt')->each(function (Crawler $label) use (&$fields): void {
            $value = $label->nextAll()->first();
            $key = $this->normalizeText($label->text(''));
            $text = $value->count() > 0 ? $this->normalizeText($value->text('')) : null;
            if ($key !== null && $text !== null) {
                $fields[rtrim($key, ':')] = $text;
            }
        });

        $crawler->filter('.movie-spec')->each(function (Crawler $item) use (&$fields): void {
            $key = $this->firstText($item, '.spec-title');
            $value = $this->firstText($item, '.spec-content');
            if ($key !== null && $value !== null) {
                $fields[rtrim($key, ':')] = $value;
            }
        });

        $crawler->filter('.p-workPage__table .item')->each(function (Crawler $item) use (&$fields): void {
            $key = $this->firstText($item, '.th');
            $value = $this->firstText($item, '.td');
            if ($key !== null && $value !== null) {
                $fields[rtrim($key, ':')] = $value;
            }
        });

        return $fields;
    }

    /**
     * @param list<string> $selectors
     * @return list<string>
     */
    private function textsFromSelectors(Crawler $crawler, array $selectors): array
    {
        $values = [];
        foreach ($selectors as $selector) {
            foreach ($this->texts($crawler, $selector) as $value) {
                if (strtolower($value) !== 'unknown') {
                    $values[$value] = true;
                }
            }
        }

        return array_keys($values);
    }

    /** @return list<array{url: string, thumbnail_url: ?string}> */
    private function screenshots(Crawler $crawler, string $baseUrl): array
    {
        $screenshots = [];
        foreach ($this->definition->screenshotSelectors as $selector) {
            $crawler->filter($selector)->each(function (Crawler $node) use (&$screenshots, $baseUrl): void {
                $href = $node->attr('href') ?? $node->attr('data-vue-img-src');
                if (! is_string($href) || trim($href) === '') {
                    return;
                }

                $thumbnail = $node->filter('img')->count() > 0
                    ? $node->filter('img')->first()->attr('src')
                    : null;
                $screenshots[$href] = [
                    'url' => $this->absolute($baseUrl, $href),
                    'thumbnail_url' => is_string($thumbnail) && trim($thumbnail) !== ''
                        ? $this->absolute($baseUrl, $thumbnail)
                        : null,
                ];
            });
        }

        return array_values($screenshots);
    }

    /** @param array<string, string> $fields */
    private function date(array $fields): ?string
    {
        foreach (['Release Date', '発売日', '公開日', '配信開始日'] as $key) {
            if (isset($fields[$key]) && preg_match('/\d{4}[\/-]\d{1,2}[\/-]\d{1,2}/', $fields[$key], $match) === 1) {
                return str_replace('/', '-', $match[0]);
            }
        }

        return null;
    }

    /** @param array<string, string> $fields */
    private function duration(array $fields): ?int
    {
        foreach (['Duration', '再生時間', '収録時間', 'Play'] as $key) {
            $value = $fields[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            if (preg_match('/(?:(\d+):)?(\d{1,2}):(\d{2})/', $value, $match) === 1) {
                return ((int) $match[1] * 60) + (int) $match[2];
            }

            if (preg_match('/(\d+)\s*(?:min|分)/i', $value, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }
}
