<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Smartflash\Types;

use JOOservices\CrawlerX\Adapters\Concerns\CoercesNumbers;
use JOOservices\CrawlerX\Adapters\Smartflash\Selectors;
use JOOservices\CrawlerX\Adapters\Smartflash\Urls;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends SmartflashHtmlType implements TypeInterface
{
    use CoercesNumbers;

    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $url = Urls::article($request->url);
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $url, type: $request->type));
        $viewerUrl = $this->viewerUrl($crawler, $url);
        $images = $this->articleImages($crawler, $url);
        if ($viewerUrl !== null) {
            $viewer = $this->fetchCrawler(new CrawlRequestDto(url: $viewerUrl, type: $request->type));
            $images = $this->merge($images, $this->viewerImages($viewer, $viewerUrl));
        }

        $title = $this->firstText($crawler, Selectors::ARTICLE_TITLE);
        $photos = $this->photos($images);
        if ($title === null || $photos === []) {
            throw new CrawlParseException('Smartflash gallery page did not contain expected gallery fields.');
        }

        $jsonLd = $this->jsonLd($crawler);
        $published = $this->isoDateTime($this->stringValue($jsonLd['datePublished'] ?? null))
            ?? $this->subHeadDate($crawler, '記事投稿日');
        $modified = $this->isoDateTime($this->stringValue($jsonLd['dateModified'] ?? null))
            ?? $this->subHeadDate($crawler, '最終更新日');
        $category = $this->firstText($crawler, Selectors::ARTICLE_CATEGORY);
        $source = $crawler->filter(Selectors::ARTICLE_SOURCE);
        $author = $crawler->filter(Selectors::ARTICLE_AUTHOR);
        $authorUrl = $this->firstAttribute($crawler, Selectors::ARTICLE_AUTHOR, 'href');
        $authorImage = $this->firstAttribute($crawler, Selectors::ARTICLE_AUTHOR_IMAGE, 'src');
        $body = $this->bodyParts($crawler);

        return GalleryDto::item(
            url: $url,
            externalId: Urls::externalId($url),
            title: $title,
            data: [
                'photo_count' => count($photos),
                'date' => MovieMeta::isoDate($published),
                'uploader' => $author->count() === 0 ? null : $this->normalizeText($author->first()->text('')),
                'uploader_url' => $authorUrl === null ? null : $this->absolute($url, $authorUrl),
                'performers' => array_values(array_unique(array_filter(array_map(
                    static fn(PhotoDto $photo): ?string => is_string($photo->metadata['caption'] ?? null) ? $photo->metadata['caption'] : null,
                    $photos,
                ), static fn(?string $caption): bool => $caption !== null))),
                'categories' => $category === null ? [] : [$category],
                'tags' => $this->texts($crawler, Selectors::ARTICLE_TAGS),
                'photos' => $photos,
                'metadata' => MovieMeta::filter([
                    'published_at' => $published,
                    'modified_at' => $modified,
                    'tag_urls' => $this->hrefs($crawler, Selectors::ARTICLE_TAGS, $url),
                    'source' => $source->count() === 0 ? null : [
                        'name' => $this->normalizeText($source->first()->text('')),
                        'url' => $this->absolute($url, (string) $source->first()->attr('href')),
                    ],
                    'author_image_url' => $authorImage === null ? null : $this->absolute($url, $authorImage),
                    'author_bio' => $this->firstText($crawler, Selectors::ARTICLE_AUTHOR_BIO),
                    'cover' => $this->cover($jsonLd),
                    'viewer_url' => $viewerUrl,
                    'description' => $this->firstAttribute($crawler, 'meta[property="og:description"]', 'content'),
                    'body' => $body['paragraphs'],
                    'profile' => $body['profile'],
                    'credits' => $body['credits'],
                ]),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articleImages(Crawler $crawler, string $baseUrl): array
    {
        return array_values(array_filter($crawler->filter(Selectors::ARTICLE_IMAGES)->each(function (Crawler $img) use ($baseUrl): ?array {
            $src = $this->imageSource($img);
            $caption = $img->closest('.wp-caption')?->filter('.wp-caption-text');

            return $src === null ? null : MovieMeta::filter([
                'url' => $this->absolute($baseUrl, $src),
                'alt' => $this->normalizeText((string) $img->attr('alt')),
                'caption' => $caption === null || $caption->count() === 0 ? null : $this->normalizeText($caption->text('')),
                'width' => $this->positiveInt($img->attr('width')),
                'height' => $this->positiveInt($img->attr('height')),
            ]);
        }), static fn(?array $image): bool => $image !== null));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function viewerImages(Crawler $viewer, string $viewerUrl): array
    {
        return array_values(array_filter($viewer->filter(Selectors::VIEWER_ITEMS)->each(function (Crawler $item) use ($viewerUrl): ?array {
            $src = $this->firstAttribute($item, 'img', 'src') ?? $this->firstAttribute($item, 'img', 'data-src');
            $index = $this->firstText($item, '.imageIndex');

            return $src === null ? null : MovieMeta::filter([
                'url' => $this->absolute($viewerUrl, $src),
                'alt' => $this->normalizeText((string) $this->firstAttribute($item, 'img', 'alt')),
                'caption' => $this->firstText($item, '.imageCaption'),
                'viewer_index' => $index !== null && preg_match('#^(\d+)/\d+$#', $index, $match) === 1 ? (int) $match[1] : null,
            ]);
        }), static fn(?array $image): bool => $image !== null));
    }

    /**
     * Keeps first-seen order and fills fields an earlier sighting of the same image lacked.
     *
     * @param  list<array<string, mixed>>  $first
     * @param  list<array<string, mixed>>  $second
     * @return list<array<string, mixed>>
     */
    private function merge(array $first, array $second): array
    {
        $images = [];
        foreach ([...$first, ...$second] as $image) {
            $key = $this->stringValue($image['url'] ?? null) ?? '';
            $images[$key] = isset($images[$key]) ? $images[$key] + $image : $image;
        }

        return array_values($images);
    }

    /**
     * @param  list<array<string, mixed>>  $images
     * @return list<PhotoDto>
     */
    private function photos(array $images): array
    {
        $photos = [];
        foreach ($images as $image) {
            $url = $this->stringValue($image['url'] ?? null) ?? '';
            unset($image['url']);

            $photos[] = new PhotoDto(
                url: $url,
                imageUrl: $url,
                position: count($photos) + 1,
                metadata: MovieMeta::filter($image + ['mime_type' => Urls::mimeType($url)]),
            );
        }

        return $photos;
    }

    private function viewerUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $href = $this->firstAttribute($crawler, Selectors::VIEWER_LINK, 'href');

        return $href === null ? null : $this->absolute($baseUrl, $href);
    }

    /**
     * @return array{paragraphs: list<string>, profile: ?array{reading: ?string, text: string}, credits: list<array{role: string, name: string}>}
     */
    private function bodyParts(Crawler $crawler): array
    {
        $parts = ['paragraphs' => [], 'profile' => null, 'credits' => []];
        $crawler->filter(Selectors::ARTICLE_BODY . ' > p')->each(function (Crawler $paragraph) use (&$parts): void {
            $rawLines = preg_split('#<br\s*/?>#i', $paragraph->html(''));
            $lines = array_values(array_filter(array_map(
                fn(string $line): string => $this->clean(strip_tags($line)),
                $rawLines === false ? [] : $rawLines,
            ), static fn(string $line): bool => $line !== ''));
            if ($lines === []) {
                return;
            }

            $credits = array_map(static fn(string $line): ?array => preg_match('#^([^・「」。、（）]{1,12})・(.+)$#u', $line, $match) === 1
                ? ['role' => $match[1], 'name' => $match[2]]
                : null, $lines);
            $reading = $paragraph->filter('strong');

            if (! in_array(null, $credits, true)) {
                $parts['credits'] = [...$parts['credits'], ...$credits];
            } elseif ($reading->count() > 0 && $parts['profile'] === null && $this->clean($reading->first()->text('')) === $lines[0]) {
                $parts['profile'] = ['reading' => $lines[0], 'text' => implode(' ', array_slice($lines, 1))];
            } else {
                $parts['paragraphs'][] = implode("\n", $lines);
            }
        });

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $jsonLd
     * @return array<string, mixed>|null
     */
    private function cover(array $jsonLd): ?array
    {
        $image = is_array($jsonLd['image'] ?? null) ? $jsonLd['image'] : [];
        $url = $this->stringValue($image['url'] ?? null);

        return $url === null ? null : MovieMeta::filter([
            'url' => $url,
            'width' => $this->positiveInt($image['width'] ?? null),
            'height' => $this->positiveInt($image['height'] ?? null),
            'mime_type' => Urls::mimeType($url),
        ]);
    }

    /** @return array<string, mixed> */
    private function jsonLd(Crawler $crawler): array
    {
        foreach ($crawler->filter(Selectors::JSON_LD)->each(static fn(Crawler $node): string => $node->text('')) as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['@type'] ?? null) === 'NewsArticle') {
                return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
            }
        }

        return [];
    }

    /** "2026-10-10 20:00:40" (JSON-LD, Japan time) to ISO 8601 with offset. */
    private function isoDateTime(?string $value): ?string
    {
        return $value !== null && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})$/', $value, $match) === 1
            ? $match[1] . 'T' . $match[2] . '+09:00'
            : null;
    }

    /** "記事投稿日：2026.10.10 20:00" to ISO 8601 with offset, for pages without JSON-LD. */
    private function subHeadDate(Crawler $crawler, string $label): ?string
    {
        $text = $this->firstText($crawler, '#main .subHeadDate') ?? '';

        return preg_match('/' . $label . '：(\d{4})\.(\d{2})\.(\d{2}) (\d{2}:\d{2})/u', $text, $match) === 1
            ? sprintf('%s-%s-%sT%s:00+09:00', $match[1], $match[2], $match[3], $match[4])
            : null;
    }

    private function imageSource(Crawler $img): ?string
    {
        foreach (['data-src', 'data-original', 'src'] as $attribute) {
            $value = $img->attr($attribute);
            if (is_string($value) && trim($value) !== '' && ! str_starts_with(trim($value), 'data:')) {
                return trim($value);
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', html_entity_decode($text))));
    }
}
