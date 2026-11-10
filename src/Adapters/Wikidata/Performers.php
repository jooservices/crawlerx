<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

use JOOservices\CrawlerX\Dto\Entity\PerformerDto;

/**
 * Builds the person entity from a Wikidata item. The listing has no labels for referenced items, so entity values
 * keep their ids there; the detail resolves labels with extra requests.
 */
final class Performers
{
    private const PROP_BIRTH_DATE = 'P569';

    private const PROP_BIRTH_PLACE = 'P19';

    private const PROP_HEIGHT = 'P2048';

    private const PROP_GENDER = 'P21';

    private const PROP_CITIZENSHIP = 'P27';

    private const PROP_OCCUPATION = 'P106';

    private const PROP_IMAGE = 'P18';

    private const PROP_WEBSITE = 'P856';

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels  labels of the properties and referenced items (empty in listings)
     */
    public static function fromEntity(array $entity, array $labels = []): ?PerformerDto
    {
        $id = $entity['id'] ?? null;
        if (! is_string($id)) {
            return null;
        }

        $japanese = WikidataApi::label($entity, 'ja');
        $english = WikidataApi::label($entity, 'en');
        $name = $english ?? $japanese ?? $id;
        $images = self::images($entity, $labels);
        $occupations = self::labelsOf($entity, self::PROP_OCCUPATION, $labels);

        return new PerformerDto(
            externalId: $id,
            name: $name,
            nameJapanese: $japanese,
            url: Site::entityPageUrl($id),
            profileImageUrl: $images[0]['url'] ?? null,
            birthDateRaw: self::date($entity, $labels),
            heightRaw: self::height($entity, $labels),
            aliases: self::aliases($entity, $japanese === null ? [$name] : [$name, $japanese]),
            tags: $occupations,
            rawProfile: self::rawProfile($entity, $labels, $occupations),
            metadata: array_filter([
                'wikidata_id' => $id,
                'labels' => self::languageValues($entity, 'labels'),
                'descriptions' => self::languageValues($entity, 'descriptions'),
                'sitelinks' => self::sitelinks($entity),
                'images' => $images,
                'statements' => Statements::normalize($entity, $labels),
                'last_revision_id' => $entity['lastrevid'] ?? null,
                'modified' => $entity['modified'] ?? null,
            ], static fn(mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        );
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     */
    private static function date(array $entity, array $labels): ?string
    {
        $value = Statements::valuesOf($entity, self::PROP_BIRTH_DATE, $labels)[0] ?? null;
        $date = is_array($value) ? ($value['date'] ?? null) : null;

        return is_string($date) ? $date : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     */
    private static function height(array $entity, array $labels): ?string
    {
        $value = Statements::valuesOf($entity, self::PROP_HEIGHT, $labels)[0] ?? null;
        if (! is_array($value) || ! is_string($value['amount'] ?? null)) {
            return null;
        }

        $unit = ($value['unit'] ?? null) === 'Q174728' ? 'cm' : ($value['unit_label'] ?? $value['unit'] ?? null);

        return $value['amount'] . (is_string($unit) ? ' ' . $unit : '');
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private static function labelsOf(array $entity, string $property, array $labels): array
    {
        $result = [];
        foreach (Statements::valuesOf($entity, $property, $labels) as $value) {
            $text = is_array($value) ? ($value['label'] ?? $value['id'] ?? null) : null;
            if (is_string($text)) {
                $result[] = $text;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<string>  $exclude
     * @return list<string>
     */
    private static function aliases(array $entity, array $exclude): array
    {
        $aliases = [];
        foreach (WikidataApi::assoc($entity['aliases'] ?? []) as $list) {
            foreach (is_array($list) ? $list : [] as $alias) {
                $value = WikidataApi::assoc($alias)['value'] ?? null;
                if (is_string($value) && $value !== '' && ! in_array($value, $exclude, true)) {
                    $aliases[] = $value;
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private static function languageValues(array $entity, string $key): array
    {
        $values = [];
        foreach (WikidataApi::assoc($entity[$key] ?? []) as $language => $entry) {
            $value = WikidataApi::assoc($entry)['value'] ?? null;
            if (is_string($value) && $value !== '') {
                $values[$language] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, string>
     */
    private static function sitelinks(array $entity): array
    {
        $links = [];
        foreach (WikidataApi::assoc($entity['sitelinks'] ?? []) as $site => $link) {
            $url = WikidataApi::assoc($link)['url'] ?? null;
            if (is_string($url)) {
                $links[$site] = $url;
            }
        }

        return $links;
    }

    /**
     * Every image statement (P18) as the original Commons file plus a thumbnail form.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @return list<array{url: string, thumbnail_url: string, file: string}>
     */
    private static function images(array $entity, array $labels): array
    {
        $images = [];
        foreach (Statements::valuesOf($entity, self::PROP_IMAGE, $labels) as $file) {
            if (is_string($file) && $file !== '') {
                $images[] = ['url' => Site::fileUrl($file), 'thumbnail_url' => Site::thumbnailUrl($file), 'file' => $file];
            }
        }

        return $images;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @param  list<string>  $occupations
     * @return array<string, mixed>
     */
    private static function rawProfile(array $entity, array $labels, array $occupations): array
    {
        $descriptions = self::languageValues($entity, 'descriptions');
        $website = Statements::valuesOf($entity, self::PROP_WEBSITE, $labels)[0] ?? null;

        return array_filter([
            'description_ja' => $descriptions['ja'] ?? null,
            'description_en' => $descriptions['en'] ?? null,
            'birth_place' => self::labelsOf($entity, self::PROP_BIRTH_PLACE, $labels)[0] ?? null,
            'gender' => self::labelsOf($entity, self::PROP_GENDER, $labels)[0] ?? null,
            'citizenship' => self::labelsOf($entity, self::PROP_CITIZENSHIP, $labels),
            'occupation' => $occupations,
            'official_website' => is_string($website) ? $website : null,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }
}
