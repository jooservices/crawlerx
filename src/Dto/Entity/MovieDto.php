<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto\Entity;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class MovieDto extends Dto
{
    /**
     * @param list<PerformerDto> $performers
     * @param list<string> $tags
     * @param list<ScreenshotDto> $screenshots
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[MapTo('external_id')]
        public readonly ?string $externalId = null,
        public readonly ?string $title = null,
        public readonly ?string $code = null,
        #[MapTo('cover_url')]
        public readonly ?string $coverUrl = null,
        public readonly ?string $description = null,
        public readonly ?string $date = null,
        public readonly ?int $duration = null,
        public readonly array $performers = [],
        public readonly array $tags = [],
        public readonly array $screenshots = [],
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromParsed(?string $externalId, ?string $title, array $data = []): self
    {
        $metadata = self::associative($data['metadata'] ?? null);
        $known = [
            'external_id', 'title', 'code', 'cover_url', 'description', 'date',
            'duration', 'performers', 'tags', 'screenshots', 'metadata',
        ];
        foreach ($data as $key => $value) {
            if (! in_array($key, $known, true)) {
                $metadata[$key] = $value;
            }
        }

        return new self(
            externalId: self::string($data['external_id'] ?? $externalId),
            title: self::string($data['title'] ?? $title),
            code: self::string($data['code'] ?? null),
            coverUrl: self::string($data['cover_url'] ?? null),
            description: self::string($data['description'] ?? null),
            date: self::string($data['date'] ?? null),
            duration: is_int($data['duration'] ?? null) ? $data['duration'] : null,
            performers: self::performers($data['performers'] ?? []),
            tags: self::stringList($data['tags'] ?? []),
            screenshots: self::screenshots($data['screenshots'] ?? []),
            metadata: $metadata,
        );
    }

    public function toItem(string $url): CrawlItemResultDto
    {
        return new CrawlItemResultDto(
            url: $url,
            entityType: 'movie',
            meta: ['movie' => $this->toArray()],
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

    /** @return list<PerformerDto> */
    private static function performers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $performers = [];
        foreach ($value as $entry) {
            if ($entry instanceof PerformerDto) {
                $performers[] = $entry;
            } elseif (is_string($entry) && trim($entry) !== '') {
                $performers[] = new PerformerDto(name: trim($entry));
            } elseif (is_array($entry)) {
                $parsed = self::associative($entry);
                $performers[] = PerformerDto::fromParsed(
                    self::string($parsed['external_id'] ?? null),
                    self::string($parsed['name'] ?? null),
                    $parsed,
                    self::string($parsed['url'] ?? null),
                );
            }
        }

        return $performers;
    }

    /** @return list<ScreenshotDto> */
    private static function screenshots(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $screenshots = [];
        foreach ($value as $entry) {
            if ($entry instanceof ScreenshotDto) {
                $screenshots[] = $entry;
            } elseif (is_string($entry) && trim($entry) !== '') {
                $screenshots[] = new ScreenshotDto(trim($entry));
            } elseif (is_array($entry) && is_string($entry['url'] ?? null)) {
                $screenshots[] = ScreenshotDto::fromParsed(self::associative($entry));
            }
        }

        return $screenshots;
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
