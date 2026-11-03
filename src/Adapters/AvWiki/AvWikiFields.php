<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

use DateTimeImmutable;
use DateTimeZone;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class AvWikiFields
{
    /**
     * @param array<int|string, array<string, mixed>> $tags
     * @param array<int|string, array<string, mixed>> $categories
     * @param array<string, mixed> $post
     */
    public static function movie(array $post, array $tags, array $categories, ?string $pageHtml = null): CrawlItemResultDto
    {
        $title = self::rendered($post['title'] ?? null);
        $slug = self::string($post['slug'] ?? null);
        $guid = is_array($post['guid'] ?? null) ? self::string($post['guid']['rendered'] ?? null) : null;
        $url = self::string($post['link'] ?? null) ?? $guid ?? Site::BASE_URL . '/' . rawurlencode((string) $slug) . '/';
        $externalId = self::stringId($post['id'] ?? null);
        $content = self::associative($post['content'] ?? null);
        $contentHtml = self::string($content['rendered'] ?? null) ?? '';
        $articleMarkup = self::pageArticle($pageHtml) ?? $contentHtml;
        $article = self::article($articleMarkup, $pageHtml);
        $fields = self::articleFields($articleMarkup);
        $tagRows = self::linkedTerms($post['tags'] ?? [], $tags);
        $categoryRows = self::linkedTerms($post['categories'] ?? [], $categories);
        $performers = array_map(self::performer(...), $tagRows);
        $dateText = $fields['配信開始日'] ?? null;
        $pageHeading = self::pageHeading($pageHtml) ?? $title;
        $pageDate = self::pageDate($pageHtml);
        $postDate = self::isoDateTime($post['date'] ?? null, 'Asia/Tokyo');
        $postDateGmt = self::isoDateTime($post['date_gmt'] ?? null, 'UTC');
        $modified = self::isoDateTime($post['modified'] ?? null, 'Asia/Tokyo');
        $modifiedGmt = self::isoDateTime($post['modified_gmt'] ?? null, 'UTC');

        $metadata = MovieMeta::filter([
            MovieMeta::MAKER => $fields['メーカー'] ?? null,
            MovieMeta::LABEL => $fields['レーベル'] ?? null,
            MovieMeta::SERIES => $fields['シリーズ'] ?? null,
            MovieMeta::FIELDS => $fields,
            MovieMeta::TITLES => ['page_heading' => $pageHeading],
            MovieMeta::POSTED_DATE => $postDate,
            'page_date' => $pageDate,
            'posted_date_gmt' => $postDateGmt,
            'modified_date' => $modified,
            'modified_date_gmt' => $modifiedGmt,
            'release_date_raw' => $dateText,
            'amateur_name' => $fields['素人名義'] ?? null,
            'mgs_code' => $fields['MGS品番'] ?? null,
            'fanza_code' => $fields['FANZA品番'] ?? null,
            'links' => $article['retailer_links'],
            'article_text' => $article['text'],
            'author' => self::embeddedAuthor($post),
            'category_ids' => self::ids($post['categories'] ?? null),
            'tag_ids' => self::ids($post['tags'] ?? null),
            'categories' => $categoryRows,
            'wordpress' => self::wordpressFields($post, $article['text']),
        ]);

        return MovieDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: MovieMeta::filter([
                'external_id' => $externalId,
                'code' => $fields['メーカー品番'] ?? $fields['MGS品番'] ?? $fields['FANZA品番'] ?? null,
                'cover_url' => $article['cover_url'],
                'date' => MovieMeta::isoDate(is_string($dateText) ? $dateText : null),
                'performers' => $performers,
                'tags' => array_values(array_filter(array_map(
                    static fn(array $term): ?string => self::string($term['name'] ?? null),
                    $tagRows,
                ), static fn(?string $tag): bool => $tag !== null && $tag !== '')),
                'screenshots' => $article['screenshots'],
                'metadata' => $metadata,
            ]),
        );
    }

    /**
     * The embedded `post_tag` term only carries identity fields; the actor profile (birth date, size,
     * aliases, SNS) lives in the tag description that needs a separate `/tags` request, which AV-Wiki's
     * per-request throttle makes too expensive to do on every crawl.
     *
     * @param array<string, mixed> $term
     * @return array<string, mixed>
     */
    private static function performer(array $term): array
    {
        $name = self::string($term['name'] ?? null);

        return MovieMeta::filter([
            'external_id' => self::stringId($term['id'] ?? null),
            'name' => $name,
            'name_japanese' => $name,
            'url' => self::string($term['link'] ?? null),
            'metadata' => [
                'taxonomy' => 'post_tag',
                'slug' => self::string($term['slug'] ?? null),
                'wordpress' => $term,
            ],
        ]);
    }

    private static function pageArticle(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $crawler = new Crawler($html, Site::BASE_URL);
        $body = $crawler->filter('section.article-body');

        return $body->count() > 0 ? $body->first()->html() : null;
    }

    private static function pageHeading(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $crawler = new Crawler($html, Site::BASE_URL);
        $heading = $crawler->filter('h1.entry-title');

        return $heading->count() > 0 ? self::cleanText($heading->first()->text('')) : null;
    }

    private static function pageDate(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $crawler = new Crawler($html, Site::BASE_URL);
        $date = $crawler->filter('article time[datetime]');
        if ($date->count() === 0) {
            return null;
        }
        $value = $date->first()->attr('datetime');

        return MovieMeta::isoDate(is_string($value) ? $value : null);
    }

    /** @return array{cover_url: ?string, screenshots: list<array{url: string, thumbnail_url: ?string, position: int}>, retailer_links: list<array{label: string, url: string}>, text: ?string} */
    private static function article(string $html, ?string $pageHtml): array
    {
        $crawler = new Crawler('<div>' . $html . '</div>', Site::BASE_URL);
        $crawler->filter('.cat-link, .border-left, .share, .share-buttons, .social-buttons')->each(static function (Crawler $node): void {
            $element = $node->getNode(0);
            if ($element?->parentNode !== null) {
                $element->parentNode->removeChild($element);
            }
        });

        $text = self::cleanText($crawler->filter('div')->first()->text(''));
        $imageCrawler = $pageHtml !== null && trim($pageHtml) !== ''
            ? (new Crawler($pageHtml, Site::BASE_URL))->filter('article')
            : $crawler->filter('div');
        $imageUrls = [];
        $imageCrawler->filter('img')->each(static function (Crawler $image) use (&$imageUrls): void {
            $dataSource = $image->attr('data-src');
            $source = is_string($dataSource) && trim($dataSource) !== '' ? $dataSource : $image->attr('src');
            if (! is_string($source) || trim($source) === '' || str_starts_with($source, 'data:')) {
                return;
            }
            $absolute = Site::absoluteUrl($source);
            $host = parse_url($absolute, PHP_URL_HOST);
            if (is_string($host) && strtolower($host) !== 'av-wiki.net') {
                $imageUrls[$absolute] = $absolute;
            }
        });

        $cover = array_values($imageUrls)[0] ?? null;
        $coverHost = is_string($cover) ? strtolower((string) parse_url($cover, PHP_URL_HOST)) : null;
        $screenshots = [];
        foreach (array_values($imageUrls) as $url) {
            if ($url === $cover || $coverHost === null || strtolower((string) parse_url($url, PHP_URL_HOST)) !== $coverHost) {
                continue;
            }
            $screenshots[] = [
                'url' => $url,
                'thumbnail_url' => null,
                'position' => count($screenshots) + 1,
            ];
        }

        $retailerLinks = [];
        $crawler->filter('a[href]')->each(static function (Crawler $link) use (&$retailerLinks): void {
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }
            $absolute = Site::absoluteUrl($href);
            $host = strtolower((string) parse_url($absolute, PHP_URL_HOST));
            if ($host === '' || $host === 'av-wiki.net' || preg_match('/(?:twitter|x\.com|hatena|pocket|line\.me|facebook)/i', $host) === 1) {
                return;
            }
            $retailerLinks[$absolute] = ['label' => self::cleanText($link->text('')), 'url' => $absolute];
        });

        return [
            'cover_url' => $cover,
            'screenshots' => $screenshots,
            'retailer_links' => array_values($retailerLinks),
            'text' => $text !== '' ? $text : null,
        ];
    }

    /** @return array<string, string> */
    private static function articleFields(string $html): array
    {
        $crawler = new Crawler('<div>' . $html . '</div>', Site::BASE_URL);
        $crawler->filter('.cat-link, .border-left, .share, .share-buttons, .social-buttons')->each(static function (Crawler $node): void {
            $element = $node->getNode(0);
            if ($element?->parentNode !== null) {
                $element->parentNode->removeChild($element);
            }
        });

        $fields = [];
        $crawler->filter('dl.dltable')->each(static function (Crawler $list) use (&$fields): void {
            $list->filter('dt')->each(static function (Crawler $labelNode) use (&$fields): void {
                $label = self::cleanText($labelNode->text(''));
                $value = self::followingSiblingText($labelNode, 'dd');
                if ($label !== '' && $value !== '') {
                    $fields[$label] = $value;
                }
            });
        });

        return $fields;
    }

    /** @param mixed $rawIds
     *  @param array<int|string, array<string, mixed>> $resolved
     *  @return list<array<string, mixed>>
     */
    private static function linkedTerms(mixed $rawIds, array $resolved): array
    {
        if (! is_array($rawIds)) {
            return [];
        }

        $terms = [];
        foreach ($rawIds as $id) {
            $key = is_int($id) || (is_string($id) && ctype_digit($id)) ? (string) $id : null;
            if ($key !== null && isset($resolved[$key])) {
                $terms[] = $resolved[$key];
            }
        }

        return $terms;
    }

    /** @param array<string, mixed> $post
     *  @return array<string, mixed>
     */
    private static function wordpressFields(array $post, ?string $articleText): array
    {
        $wordpress = $post;
        $wordpress['featured_media_id'] = $post['featured_media'] ?? null;
        foreach (['date', 'modified'] as $field) {
            if (isset($post[$field])) {
                $wordpress[$field] = self::isoDateTime($post[$field], 'Asia/Tokyo');
            }
        }
        foreach (['date_gmt', 'modified_gmt'] as $field) {
            if (isset($post[$field])) {
                $wordpress[$field] = self::isoDateTime($post[$field], 'UTC');
            }
        }

        $content = self::associative($post['content'] ?? null);
        $wordpress['content'] = [
            'rendered_text' => $articleText,
            'protected' => $content['protected'] ?? null,
        ];
        $wordpress['content_protected'] = $content['protected'] ?? null;
        $excerpt = self::associative($post['excerpt'] ?? null);
        $wordpress['excerpt'] = [
            'rendered_text' => self::cleanText(self::rendered($post['excerpt'] ?? null) ?? ''),
            'protected' => $excerpt['protected'] ?? null,
        ];
        $wordpress['excerpt_protected'] = $excerpt['protected'] ?? null;

        return $wordpress;
    }

    /** @param array<string, mixed> $post
     *  @return array<string, mixed>|null
     */
    private static function embeddedAuthor(array $post): ?array
    {
        $embedded = self::associative($post['_embedded'] ?? null);
        $authors = $embedded['author'] ?? null;
        $author = is_array($authors) ? ($authors[0] ?? null) : null;

        return is_array($author) ? self::associative($author) : null;
    }

    /** @return list<int|string> */
    private static function ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            $values,
            static fn(mixed $value): bool => is_int($value) || is_string($value),
        ));
    }

    private static function isoDateTime(mixed $value, string $timezone): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone($timezone));
        } catch (\Exception) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i:sP');
    }

    private static function rendered(mixed $value): ?string
    {
        if (is_string($value)) {
            return self::cleanText($value);
        }
        if (is_array($value) && is_string($value['rendered'] ?? null)) {
            return self::cleanText($value['rendered']);
        }

        return null;
    }

    private static function cleanText(string $text): string
    {
        $crawler = new Crawler('<div>' . $text . '</div>', Site::BASE_URL);
        $normalized = preg_replace('/\s+/u', ' ', $crawler->filter('div')->first()->text(''));

        return trim(is_string($normalized) ? $normalized : '');
    }

    private static function followingSiblingText(Crawler $node, string $tag): string
    {
        $element = $node->getNode(0);
        $sibling = $element?->nextSibling;
        while ($sibling !== null) {
            if ($sibling instanceof \DOMElement) {
                return strtolower($sibling->tagName) === $tag ? self::cleanText($sibling->textContent) : '';
            }
            $sibling = $sibling->nextSibling;
        }

        return '';
    }

    /** @return array<string, mixed> */
    private static function associative(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $result[$key] = $entry;
            }
        }

        return $result;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function stringId(mixed $value): ?string
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (string) $value : null;
    }
}
