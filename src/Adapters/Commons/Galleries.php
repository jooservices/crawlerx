<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;

/**
 * Builds gallery entities: a single Commons file, or a category's photos.
 */
final class Galleries
{
    /**
     * One file page as a one-photo gallery carrying the file's descriptive fields.
     *
     * @param  array<string, mixed>  $page
     */
    public static function fromFile(array $page): ?CrawlItemResultDto
    {
        $photo = Photos::fromPage($page, 1);
        if ($photo === null || ! is_string($page['title'] ?? null)) {
            return null;
        }

        $meta = $photo->metadata;
        $url = $photo->url ?? Site::pageUrl($page['title']);

        return GalleryDto::item(
            url: $url,
            externalId: $photo->id,
            title: preg_replace('/^File:/', '', $page['title']),
            data: [
                'photo_count' => 1,
                'uploader' => $meta['uploader'] ?? null,
                'uploader_url' => $meta['uploader_url'] ?? null,
                'date' => $meta['date'] ?? null,
                'categories' => $meta['categories'] ?? [],
                'tags' => $meta['tags'] ?? [],
                'photos' => [$photo],
                'metadata' => array_filter([
                    'source_url' => $url,
                    'name' => $meta['name'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'author' => $meta['author'] ?? null,
                    'author_url' => $meta['author_url'] ?? null,
                    'credit' => $meta['credit'] ?? null,
                    'license' => $meta['license'] ?? null,
                    'license_url' => $meta['license_url'] ?? null,
                    'uploaded_at' => $meta['uploaded_at'] ?? null,
                ], static fn(mixed $value): bool => $value !== null),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $category  category page of the gallery
     * @param  list<PhotoDto>  $photos
     */
    public static function fromCategory(array $category, array $photos, ?string $nextUrl): ?CrawlItemResultDto
    {
        $performer = Performers::fromPage($category);
        if ($performer === null) {
            return null;
        }

        $info = CommonsApi::assoc($category['categoryinfo'] ?? []);
        $files = is_int($info['files'] ?? null) ? $info['files'] : count($photos);

        return GalleryDto::item(
            url: $performer->url ?? Site::pageUrl('Category:' . $performer->name),
            externalId: $performer->externalId,
            title: $performer->name,
            data: [
                'photo_count' => $files,
                'performers' => [(string) $performer->name],
                'categories' => $performer->tags,
                'photos' => $photos,
                'metadata' => array_filter([
                    'performer' => $performer->toArray(),
                    'files_on_page' => count($photos),
                    'next_url' => $nextUrl,
                ], static fn(mixed $value): bool => $value !== null),
            ],
        );
    }
}
