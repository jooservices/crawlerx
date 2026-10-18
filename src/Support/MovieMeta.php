<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

/**
 * Standard keys and builders for movie data that has no first-class MovieDto property.
 *
 * Every adapter writes these keys into the movie `metadata` (or passes them as extra `data:` keys, which MovieDto
 * moves into `metadata`), so a consumer reads maker, label, series, rating or magnets the same way for every site.
 * See docs/dto-conventions.md.
 */
final class MovieMeta
{
    public const MAKER = 'maker';

    public const LABEL = 'label';

    public const SERIES = 'series';

    public const DIRECTOR = 'director';

    public const RATING = 'rating';

    public const MAGNETS = 'magnets';

    public const DOWNLOADS = 'downloads';

    public const SAMPLE_VIDEO_URL = 'sample_video_url';

    public const PRICE = 'price';

    public const SELLER = 'seller';

    public const VIEWS = 'views';

    public const LIKES = 'likes';

    public const FAVORITE_COUNT = 'favorite_count';

    public const COMMENT_COUNT = 'comment_count';

    public const TITLES = 'titles';

    public const ORIGINAL_TITLE = 'original_title';

    public const RELEASE_DATE_RAW = 'release_date_raw';

    public const POSTED_DATE = 'posted_date';

    public const FIELDS = 'fields';

    /** @return array{score: float, scale: ?float, votes: ?int} */
    public static function rating(float $score, ?float $scale = null, ?int $votes = null): array
    {
        return ['score' => $score, 'scale' => $scale, 'votes' => $votes];
    }

    /**
     * @param  list<string>  $tags
     * @return array{name: ?string, url: string, size_label: ?string, size_bytes: ?int, date: ?string, tags: list<string>}
     */
    public static function magnet(string $url, ?string $name = null, ?string $sizeLabel = null, ?string $date = null, array $tags = []): array
    {
        return [
            'name' => self::clean($name),
            'url' => $url,
            'size_label' => self::clean($sizeLabel),
            'size_bytes' => SizeParser::bytes($sizeLabel),
            'date' => $date,
            'tags' => array_values(array_filter(array_map(static fn(string $tag): string => trim($tag), $tags), static fn(string $tag): bool => $tag !== '')),
        ];
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string} */
    public static function price(?float $amount, ?string $currency = null, ?string $label = null): array
    {
        return ['amount' => $amount, 'currency' => $currency, 'label' => self::clean($label)];
    }

    /** @return array{external_id: ?string, name: string, url: ?string} */
    public static function performer(string $name, ?string $externalId = null, ?string $url = null): array
    {
        return ['external_id' => $externalId, 'name' => trim($name), 'url' => $url];
    }

    /**
     * Converts a year-first date ("2026-08-14", "2026/8/4", "2026年08月 14日", "2026.08.14") to YYYY-MM-DD.
     */
    public static function isoDate(?string $text): ?string
    {
        if ($text === null || preg_match('/(?<!\d)(\d{4})\s*[-\/.年]\s*(\d{1,2})\s*[-\/.月]\s*(\d{1,2})(?!\d)/u', $text, $match) !== 1) {
            return null;
        }

        return self::validDate((int) $match[1], (int) $match[2], (int) $match[3]);
    }

    /** Converts a month-first date ("09/01/2026") to YYYY-MM-DD. */
    public static function isoDateMonthFirst(?string $text): ?string
    {
        if ($text === null || preg_match('/(?<!\d)(\d{1,2})\/(\d{1,2})\/(\d{4})(?!\d)/', $text, $match) !== 1) {
            return null;
        }

        return self::validDate((int) $match[3], (int) $match[1], (int) $match[2]);
    }

    /**
     * Duration in whole minutes from "120分", "120 min", "160 minute(s)", "1:02:12", "48分 33秒", "48:33".
     */
    public static function durationMinutes(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        if (preg_match('/(\d+):(\d{2}):(\d{2})/', $text, $match) === 1) {
            return ((int) $match[1] * 60) + (int) $match[2] + ((int) $match[3] >= 30 ? 1 : 0);
        }

        if (preg_match('/(\d+)\s*分/u', $text, $match) === 1 || preg_match('/(\d+)\s*(?:minutes?|mins?)(?:\(s\))?/i', $text, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/(?<!\d)(\d{1,3}):(\d{2})(?!\d)/', $text, $match) === 1) {
            return (int) $match[1] + ((int) $match[2] >= 30 ? 1 : 0);
        }

        return null;
    }

    /**
     * Drops null, empty strings and empty lists so a missing field is absent, not an empty value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function filter(array $data): array
    {
        return array_filter($data, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private static function validDate(int $year, int $month, int $day): ?string
    {
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    private static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }
}
