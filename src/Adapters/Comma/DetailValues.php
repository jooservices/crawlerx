<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Comma;

use JOOservices\CrawlerX\Support\MovieMeta;

/**
 * Turns the text of COMMA VISION's labelled detail rows into standard values. Pure functions, no markup.
 */
final class DetailValues
{
    /**
     * Cast members are separated by a full-width slash ("A／B／C").
     *
     * @return list<string>
     */
    public static function performers(?string $text): array
    {
        if ($text === null) {
            return [];
        }

        $names = preg_split('#[／/]#u', $text);

        return array_values(array_filter(
            array_map('trim', is_array($names) ? $names : []),
            static fn(string $name): bool => $name !== '',
        ));
    }

    /**
     * The release row prints the month and the day after a year-month link: "2023年7月07月06日" is 2023-07-06.
     */
    public static function releaseDate(?string $text): ?string
    {
        if ($text === null || preg_match('/(\d{4})年.*?(\d{1,2})月(\d{1,2})日/u', $text, $match) !== 1) {
            return null;
        }

        return MovieMeta::isoDate(sprintf('%s-%s-%s', $match[1], $match[2], $match[3]));
    }

    /**
     * The tax-included yen amount when the row prints one ("¥ 4,500- （税込 ¥4,950-）" gives 4950), else the first.
     *
     * @return array{amount: ?float, currency: ?string, label: ?string}|null
     */
    public static function price(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $amount = self::firstAmount('/税込\s*¥\s*([\d,]+)/u', $text) ?? self::firstAmount('/¥\s*([\d,]+)/u', $text);

        return $amount === null ? null : MovieMeta::price((float) str_replace(',', '', $amount), 'JPY', $text);
    }

    /**
     * The comment row holds the synopsis, then "原題 : <original title>" and the copyright line.
     *
     * @return array{description: ?string, original_title: ?string}
     */
    public static function comment(?string $text): array
    {
        if ($text === null) {
            return ['description' => null, 'original_title' => null];
        }

        $hasOriginal = preg_match('/原題\s*[:：]\s*([^©]+)/u', $text, $match) === 1;
        $description = trim((string) preg_replace('/原題\s*[:：].*$/u', '', $text));

        return [
            'description' => $description === '' ? null : $description,
            'original_title' => $hasOriginal ? trim($match[1]) : null,
        ];
    }

    private static function firstAmount(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $match) === 1 ? $match[1] : null;
    }
}
