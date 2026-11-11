<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Naturalhigh\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Fields;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Site;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    protected function siteLabel(): string
    {
        return Site::SLUG;
    }

    protected function contextLabel(): string
    {
        return 'detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $dom = $this->fetchCrawler($request);
        $fields = new Fields();
        $body = $dom->filter('main, article, .detail, .product, .work')->first();
        if ($body->count() === 0) {
            $body = $dom;
        }
        $rows = $fields->rows($body);
        $sourceFields = $fields->sourceFields($rows);
        $code = $this->first($sourceFields, ['品番', '品番 / 品名', '商品番号', '品番：'])
            ?? strtoupper(basename((string) parse_url($request->url, PHP_URL_PATH)));
        $title = $this->first($sourceFields, ['タイトル', '商品名']) ?? $this->text($body, 'h1');
        $dateRaw = $this->first($sourceFields, ['発売日', '発売日：', '配信日']);
        $images = $this->images($body, $fields);
        if ($title === null || $sourceFields === []) {
            throw new CrawlParseException('Naturalhigh detail page did not contain expected work fields.');
        }
        $performers = [];
        foreach (['出演', '出演者', '女優'] as $label) {
            if (isset($rows[$label])) {
                $performers = $fields->links($rows[$label]);
                break;
            }
        }
        $label = $this->first($sourceFields, ['レーベル', 'メーカー']);
        $series = $this->first($sourceFields, ['シリーズ', 'シリーズ作品']);
        $director = $this->first($sourceFields, ['監督']);
        $genres = $rows['ジャンル'] ?? null;
        $tags = $genres === null
            ? []
            : array_values(array_filter(
                array_map('trim', explode('/', (string) $fields->text($genres))),
                static fn(string $tag): bool => $tag !== '',
            ));
        $duration = MovieMeta::durationMinutes($this->first($sourceFields, ['収録時間', '再生時間']));
        $item = MovieDto::item($request->url, $code, $title, [
            'code' => $code,
            'date' => $fields->date($dateRaw),
            'duration' => $duration,
            'description' => $sourceFields['作品紹介'] ?? $this->description($body),
            'cover_url' => $images[0]['url'] ?? null,
            'screenshots' => $images,
            'performers' => $performers,
            'tags' => $tags,
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => Site::NAME,
                MovieMeta::LABEL => $label,
                MovieMeta::SERIES => $series,
                MovieMeta::DIRECTOR => $director,
                'genres' => $genres === null ? [] : array_map(
                    static fn(string $name): array => ['name' => trim($name)],
                    array_filter(explode('/', (string) $fields->text($genres)), static fn(string $name): bool => trim($name) !== ''),
                ),
                'source_fields' => $sourceFields,
            ]),
        ]);
        $this->assertUsableMovieDetail($item, 'Naturalhigh');

        return $item;
    }

    /** @return list<array{url: string, thumbnail_url: ?string, position: int}> */
    private function images(Crawler $body, Fields $fields): array
    {
        $images = [];
        foreach ($body->filter('.single_main_image a[href], .p-style__gallery a[href]') as $element) {
            $link = new Crawler($element);
            $image = $link->filter('img')->first();
            if ($image->count() === 0) {
                continue;
            }
            $url = $fields->resolve($link->attr('href'));
            if ($url === null || preg_match('/\.(?:jpe?g|png|webp)(?:\?|$)/i', $url) !== 1) {
                continue;
            }
            $thumbnailUrl = $this->imageSource($image);
            $images[$url] = [
                'url' => $url,
                'thumbnail_url' => $fields->resolve($thumbnailUrl),
                'position' => count($images) + 1,
            ];
        }
        return array_values($images);
    }

    private function imageSource(Crawler $image): ?string
    {
        foreach (['data-lazy-src', 'data-src', 'data-original', 'src'] as $attribute) {
            $source = $image->attr($attribute);
            if ($source !== null && trim($source) !== '') {
                return $source;
            }
        }

        return null;
    }

    private function description(Crawler $body): ?string
    {
        foreach (['.description', '.comment', '.product-description', 'p'] as $selector) {
            $description = $this->text($body, $selector);
            if ($description !== null && mb_strlen($description) > 20) {
                return $description;
            }
        }

        return null;
    }

    private function text(Crawler $node, string $selector): ?string
    {
        $match = $node->filter($selector)->first();

        return $match->count() > 0 ? (new Fields())->text($match) : null;
    }

    /** @param array<string, string> $sourceFields
     * @param list<string> $labels
     */
    private function first(array $sourceFields, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (isset($sourceFields[$label])) {
                return $sourceFields[$label];
            }
        }

        return null;
    }
}
