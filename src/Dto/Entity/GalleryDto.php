<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto\Entity;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class GalleryDto extends Dto
{
    /**
     * @param list<string> $performers
     * @param list<string> $categories
     * @param list<string> $tags
     * @param list<PhotoDto> $photos
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[MapTo('external_id')]
        public readonly ?string $externalId = null,
        public readonly ?string $title = null,
        #[MapTo('photo_count')]
        public readonly ?int $photoCount = null,
        public readonly ?int $views = null,
        public readonly ?string $rating = null,
        public readonly ?int $votes = null,
        public readonly ?string $uploader = null,
        #[MapTo('uploader_url')]
        public readonly ?string $uploaderUrl = null,
        public readonly ?string $date = null,
        public readonly array $performers = [],
        public readonly array $categories = [],
        public readonly array $tags = [],
        public readonly array $photos = [],
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromParsed(?string $externalId, ?string $title, array $data = []): self
    {
        $metadata = self::associative($data['metadata'] ?? null);
        $known = [
            'external_id', 'title', 'photo_count', 'views', 'rating', 'votes',
            'uploader', 'uploader_url', 'date', 'performers', 'categories', 'tags',
            'photos', 'metadata',
        ];
        foreach ($data as $key => $value) {
            if (! in_array($key, $known, true)) {
                $metadata[$key] = $value;
            }
        }

        return new self(
            externalId: self::string($data['external_id'] ?? $externalId),
            title: self::string($data['title'] ?? $title),
            photoCount: is_int($data['photo_count'] ?? null) ? $data['photo_count'] : null,
            views: is_int($data['views'] ?? null) ? $data['views'] : null,
            rating: self::string($data['rating'] ?? null),
            votes: is_int($data['votes'] ?? null) ? $data['votes'] : null,
            uploader: self::string($data['uploader'] ?? null),
            uploaderUrl: self::string($data['uploader_url'] ?? null),
            date: self::string($data['date'] ?? null),
            performers: self::stringList($data['performers'] ?? []),
            categories: self::stringList($data['categories'] ?? []),
            tags: self::stringList($data['tags'] ?? []),
            photos: self::photos($data['photos'] ?? []),
            metadata: $metadata,
        );
    }

    public function toItem(string $url): CrawlItemResultDto
    {
        return new CrawlItemResultDto(
            url: $url,
            entityType: 'gallery',
            meta: ['gallery' => $this->toArray()],
        );
    }

    /** @param array<string, mixed> $data */
    public static function item(string $url, ?string $externalId, ?string $title, array $data = []): CrawlItemResultDto
    {
        return self::fromParsed($externalId, $title, $data)->toItem($url);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn(mixed $entry): bool => is_string($entry) && trim($entry) !== '',
        ));
    }

    /** @return list<PhotoDto> */
    private static function photos(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $photos = [];
        foreach ($value as $entry) {
            if ($entry instanceof PhotoDto) {
                $photos[] = $entry;
            } elseif (is_array($entry)) {
                $photos[] = PhotoDto::fromParsed(self::associative($entry));
            }
        }

        return $photos;
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
}
