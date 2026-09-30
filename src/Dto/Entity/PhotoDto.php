<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto\Entity;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class PhotoDto extends Dto
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $url = null,
        #[MapTo('image_url')]
        public readonly ?string $imageUrl = null,
        #[MapTo('thumbnail_url')]
        public readonly ?string $thumbnailUrl = null,
        public readonly ?int $position = null,
        public readonly ?int $views = null,
        public readonly ?string $rating = null,
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromParsed(array $data): self
    {
        $metadata = self::associative($data['metadata'] ?? null);
        $known = [
            'id', 'url', 'image_url', 'thumbnail_url', 'position', 'views', 'rating', 'metadata',
        ];
        foreach ($data as $key => $value) {
            if (! in_array($key, $known, true)) {
                $metadata[$key] = $value;
            }
        }

        return new self(
            id: self::string($data['id'] ?? null),
            url: self::string($data['url'] ?? null),
            imageUrl: self::string($data['image_url'] ?? null),
            thumbnailUrl: self::string($data['thumbnail_url'] ?? null),
            position: is_int($data['position'] ?? null) ? $data['position'] : null,
            views: is_int($data['views'] ?? null) ? $data['views'] : null,
            rating: self::string($data['rating'] ?? null),
            metadata: $metadata,
        );
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
