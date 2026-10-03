<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;

final class RequiredFields
{
    /**
     * @return array{missing: list<string>, found: list<string>, expected: list<string>}
     */
    public static function check(CrawlListResultDto|CrawlItemResultDto $result, string $type): array
    {
        if ($result instanceof CrawlListResultDto) {
            return self::checkList($result);
        }

        $entity = self::entity($result->meta, $result->entityType);
        $expected = self::expectedForEntity($result->entityType, $type);
        $missing = [];
        $found = [];

        foreach ($expected as $field => $predicate) {
            if ($predicate($entity)) {
                $found[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        return [
            'missing' => $missing,
            'found' => $found,
            'expected' => array_keys($expected),
        ];
    }

    /** @return array{missing: list<string>, found: list<string>, expected: list<string>} */
    private static function checkList(CrawlListResultDto $result): array
    {
        $expected = ['items', 'items[].url', 'items[].identity'];
        $missing = [];
        $found = [];

        if ($result->items === []) {
            $missing[] = 'items';
        } else {
            $found[] = 'items';
            $allHaveUrls = true;
            $allHaveIdentity = true;
            foreach ($result->items as $item) {
                if (! $item instanceof CrawlItemResultDto || trim($item->url) === '') {
                    $allHaveUrls = false;
                    continue;
                }

                $entity = self::entity($item->meta, $item->entityType);
                if (! self::hasIdentity($entity, $item->entityType)) {
                    $allHaveIdentity = false;
                }
            }

            if ($allHaveUrls) {
                $found[] = 'items[].url';
            } else {
                $missing[] = 'items[].url';
            }

            if ($allHaveIdentity) {
                $found[] = 'items[].identity';
            } else {
                $missing[] = 'items[].identity';
            }
        }

        return ['missing' => $missing, 'found' => $found, 'expected' => $expected];
    }

    /** @return array<string, callable(array<string, mixed>): bool> */
    private static function expectedForEntity(string $entityType, string $pageType): array
    {
        if ($entityType === 'performer' || str_starts_with($pageType, 'performer_')) {
            return [
                'performer.name' => static fn(array $entity): bool => self::filled($entity, 'name'),
                'performer.external_id' => static fn(array $entity): bool => self::filled($entity, 'external_id'),
                'performer.profile_data' => static fn(array $entity): bool => self::hasAny($entity, [
                    'profile_image_url', 'birth_date_raw', 'size_raw', 'height_raw', 'tags', 'raw_profile',
                ]) || self::hasAny(self::arrayValue($entity, 'metadata'), ['blood_type', 'birthplace']),
            ];
        }

        if ($entityType === 'gallery' || $pageType === 'gallery') {
            return [
                'gallery.identity' => static fn(array $entity): bool => self::hasIdentity($entity, 'gallery'),
                'gallery.media' => static fn(array $entity): bool => self::hasAny($entity, ['photos', 'images', 'items', 'media']),
            ];
        }

        return [
            'movie.title' => static fn(array $entity): bool => self::filled($entity, 'title'),
            'movie.external_id' => static fn(array $entity): bool => self::filled($entity, 'external_id'),
        ];
    }

    /** @param array<string, mixed> $meta */
    private static function entity(array $meta, string $entityType): array
    {
        $entity = $meta[$entityType] ?? [];

        return is_array($entity) ? $entity : [];
    }

    /** @param array<string, mixed> $entity */
    private static function hasIdentity(array $entity, string $entityType): bool
    {
        $keys = $entityType === 'performer' ? ['external_id', 'name'] : ['external_id', 'title', 'name'];

        foreach ($keys as $key) {
            if (self::filled($entity, $key)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $entity */
    private static function filled(array $entity, string $key): bool
    {
        $value = $entity[$key] ?? null;

        return is_string($value) ? trim($value) !== '' : (is_numeric($value) && $value !== 0);
    }

    /** @param array<string, mixed> $entity @param list<string> $keys */
    private static function hasAny(array $entity, array $keys): bool
    {
        foreach ($keys as $key) {
            $value = $entity[$key] ?? null;
            if ((is_string($value) && trim($value) !== '') || (is_array($value) && $value !== [])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $entity @return array<string, mixed> */
    private static function arrayValue(array $entity, string $key): array
    {
        $value = $entity[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
