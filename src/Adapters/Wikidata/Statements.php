<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata;

/**
 * Turns the claims of a Wikidata entity into plain values. Every statement is kept (nothing is dropped): the typed
 * value, its rank and its qualifiers, grouped by property with the property label when it is known.
 */
final class Statements
{
    /**
     * Ids whose labels make the statements readable: the properties, the entities they point to (also inside
     * qualifiers) and the units of quantities.
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    public static function referencedIds(array $entity): array
    {
        $ids = [];
        foreach (self::claims($entity) as $property => $statements) {
            $ids[$property] = true;
            foreach ($statements as $statement) {
                foreach (self::snaks($statement) as $snak) {
                    foreach (self::entityIds($snak) as $id) {
                        $ids[$id] = true;
                    }
                }
            }
        }

        $own = $entity['id'] ?? null;
        if (is_string($own)) {
            unset($ids[$own]);
        }

        return array_map('strval', array_keys($ids));
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $entity, array $labels): array
    {
        $groups = [];
        foreach (self::claims($entity) as $property => $statements) {
            $values = [];
            $datatype = null;
            foreach ($statements as $statement) {
                $snak = WikidataApi::assoc($statement['mainsnak'] ?? []);
                $datatype ??= is_string($snak['datatype'] ?? null) ? $snak['datatype'] : null;
                $entry = [
                    'value' => self::value($snak, $labels),
                    'rank' => $statement['rank'] ?? null,
                    'qualifiers' => self::qualifiers($statement, $labels),
                    'statement_id' => $statement['id'] ?? null,
                ];
                $values[] = array_filter($entry, static fn(mixed $value): bool => $value !== null && $value !== []);
            }

            $groups[] = array_filter([
                'property' => $property,
                'label' => $labels[$property] ?? null,
                'datatype' => $datatype,
                'values' => $values,
            ], static fn(mixed $value): bool => $value !== null);
        }

        return $groups;
    }

    /**
     * The values of one property in preference order (preferred rank first), normalized.
     *
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @return list<mixed>
     */
    public static function valuesOf(array $entity, string $property, array $labels): array
    {
        $statements = self::claims($entity)[$property] ?? [];
        usort($statements, static fn(array $a, array $b): int => (($b['rank'] ?? '') === 'preferred') <=> (($a['rank'] ?? '') === 'preferred'));

        $values = [];
        foreach ($statements as $statement) {
            if (($statement['rank'] ?? 'normal') === 'deprecated') {
                continue;
            }

            $value = self::value(WikidataApi::assoc($statement['mainsnak'] ?? []), $labels);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $snak
     * @param  array<string, string>  $labels
     */
    public static function value(array $snak, array $labels): mixed
    {
        $datavalue = WikidataApi::assoc($snak['datavalue'] ?? []);
        $value = $datavalue['value'] ?? null;

        return match ($datavalue['type'] ?? null) {
            'string' => is_string($value) ? $value : null,
            'wikibase-entityid' => self::entity(WikidataApi::assoc($value), $labels),
            'monolingualtext' => self::monolingual(WikidataApi::assoc($value)),
            'time' => self::time(WikidataApi::assoc($value)),
            'quantity' => self::quantity(WikidataApi::assoc($value), $labels),
            'globecoordinate' => self::coordinate(WikidataApi::assoc($value)),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $time
     * @return array<string, mixed>|null
     */
    private static function time(array $time): ?array
    {
        $raw = $time['time'] ?? null;
        $precision = is_int($time['precision'] ?? null) ? $time['precision'] : null;
        if (! is_string($raw) || preg_match('/^([+-]\d{4,})-(\d{2})-(\d{2})T/', $raw, $match) !== 1) {
            return null;
        }

        $year = ltrim($match[1], '+');
        $date = match (true) {
            $precision !== null && $precision <= 9 => $year,
            $precision === 10 => $year . '-' . $match[2],
            default => $year . '-' . $match[2] . '-' . $match[3],
        };

        return ['date' => $date, 'precision' => $precision];
    }

    /**
     * @param  array<string, mixed>  $quantity
     * @param  array<string, string>  $labels
     * @return array<string, mixed>|null
     */
    private static function quantity(array $quantity, array $labels): ?array
    {
        $amount = $quantity['amount'] ?? null;
        if (! is_string($amount)) {
            return null;
        }

        $unit = is_string($quantity['unit'] ?? null) ? basename($quantity['unit']) : null;
        $unit = $unit === '1' ? null : $unit;

        return array_filter([
            'amount' => ltrim($amount, '+'),
            'unit' => $unit,
            'unit_label' => $unit === null ? null : ($labels[$unit] ?? null),
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $coordinate
     * @return array<string, float>|null
     */
    private static function coordinate(array $coordinate): ?array
    {
        $latitude = $coordinate['latitude'] ?? null;
        $longitude = $coordinate['longitude'] ?? null;

        return is_numeric($latitude) && is_numeric($longitude)
            ? ['latitude' => (float) $latitude, 'longitude' => (float) $longitude]
            : null;
    }

    /**
     * @param  array<string, mixed>  $text
     * @return array<string, string>|null
     */
    private static function monolingual(array $text): ?array
    {
        return is_string($text['text'] ?? null) && is_string($text['language'] ?? null)
            ? ['text' => $text['text'], 'language' => $text['language']]
            : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $labels
     * @return array<string, string>|null
     */
    private static function entity(array $entity, array $labels): ?array
    {
        $id = $entity['id'] ?? null;
        if (! is_string($id)) {
            return null;
        }

        return array_filter(['id' => $id, 'label' => $labels[$id] ?? null], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $statement
     * @param  array<string, string>  $labels
     * @return array<string, list<mixed>>
     */
    private static function qualifiers(array $statement, array $labels): array
    {
        $qualifiers = [];
        foreach (WikidataApi::assoc($statement['qualifiers'] ?? []) as $property => $snaks) {
            foreach (is_array($snaks) ? $snaks : [] as $snak) {
                $value = self::value(WikidataApi::assoc($snak), $labels);
                if ($value !== null) {
                    $qualifiers[$property][] = $value;
                }
            }
        }

        return $qualifiers;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, list<array<string, mixed>>>
     */
    private static function claims(array $entity): array
    {
        $claims = [];
        foreach (WikidataApi::assoc($entity['claims'] ?? []) as $property => $statements) {
            $list = [];
            foreach (is_array($statements) ? $statements : [] as $statement) {
                $list[] = WikidataApi::assoc($statement);
            }

            $claims[$property] = $list;
        }

        return $claims;
    }

    /**
     * The main snak and the qualifier snaks of a statement.
     *
     * @param  array<string, mixed>  $statement
     * @return list<array<string, mixed>>
     */
    private static function snaks(array $statement): array
    {
        $snaks = [WikidataApi::assoc($statement['mainsnak'] ?? [])];
        foreach (WikidataApi::assoc($statement['qualifiers'] ?? []) as $qualifier) {
            foreach (is_array($qualifier) ? $qualifier : [] as $snak) {
                $snaks[] = WikidataApi::assoc($snak);
            }
        }

        return $snaks;
    }

    /**
     * @param  array<string, mixed>  $snak
     * @return list<string>
     */
    private static function entityIds(array $snak): array
    {
        $datavalue = WikidataApi::assoc($snak['datavalue'] ?? []);
        $value = WikidataApi::assoc($datavalue['value'] ?? []);
        $ids = [];
        if (($datavalue['type'] ?? null) === 'wikibase-entityid' && is_string($value['id'] ?? null)) {
            $ids[] = $value['id'];
        }

        if (($datavalue['type'] ?? null) === 'quantity' && is_string($value['unit'] ?? null) && $value['unit'] !== '1') {
            $ids[] = basename($value['unit']);
        }

        return $ids;
    }
}
