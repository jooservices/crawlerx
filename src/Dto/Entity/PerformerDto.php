<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto\Entity;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class PerformerDto extends Dto
{
    /**
     * @param list<string> $aliases
     * @param list<string> $tags
     * @param array<string, mixed> $rawProfile
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        #[MapTo('external_id')]
        public readonly ?string $externalId = null,
        public readonly ?string $name = null,
        #[MapTo('name_japanese')]
        public readonly ?string $nameJapanese = null,
        public readonly ?string $url = null,
        #[MapTo('profile_image_url')]
        public readonly ?string $profileImageUrl = null,
        #[MapTo('birth_date_raw')]
        public readonly ?string $birthDateRaw = null,
        #[MapTo('height_raw')]
        public readonly ?string $heightRaw = null,
        #[MapTo('size_raw')]
        public readonly ?string $sizeRaw = null,
        public readonly array $aliases = [],
        public readonly array $tags = [],
        #[MapTo('raw_profile')]
        public readonly array $rawProfile = [],
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromParsed(
        ?string $externalId,
        ?string $name,
        array $data = [],
        ?string $url = null,
    ): self {
        $metadata = self::associative($data['metadata'] ?? null);
        $aliases = self::stringList($data['aliases'] ?? []);
        $tags = self::stringList($data['tags'] ?? []);
        $rawProfile = is_array($data['raw_profile'] ?? null)
            ? self::associative($data['raw_profile'])
            : (is_string($data['raw_profile'] ?? null) && trim($data['raw_profile']) !== ''
                ? ['text' => trim($data['raw_profile'])]
                : []);

        $known = [
            'external_id', 'name', 'name_japanese', 'url', 'profile_image_url',
            'birth_date_raw', 'height_raw', 'size_raw', 'aliases', 'tags',
            'raw_profile', 'metadata',
        ];
        foreach ($data as $key => $value) {
            if (! in_array($key, $known, true)) {
                $metadata[$key] = $value;
            }
        }

        return new self(
            externalId: self::string($data['external_id'] ?? $externalId),
            name: self::string($data['name'] ?? $name),
            nameJapanese: self::string($data['name_japanese'] ?? null),
            url: self::string($data['url'] ?? $url),
            profileImageUrl: self::string($data['profile_image_url'] ?? null),
            birthDateRaw: self::string($data['birth_date_raw'] ?? null),
            heightRaw: self::string($data['height_raw'] ?? null),
            sizeRaw: self::string($data['size_raw'] ?? null),
            aliases: $aliases,
            tags: $tags,
            rawProfile: $rawProfile,
            metadata: $metadata,
        );
    }

    public function toItem(string $url): CrawlItemResultDto
    {
        return new CrawlItemResultDto(
            url: $url,
            entityType: 'performer',
            meta: ['performer' => $this->toArray()],
        );
    }

    /** @param array<string, mixed> $data */
    public static function item(string $url, ?string $externalId, ?string $title, array $data = []): CrawlItemResultDto
    {
        return self::fromParsed($externalId, $title, $data, $url)->toItem($url);
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
