<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\OnejavTheme;

use JOOservices\CrawlerX\Support\MovieMeta;
use JOOservices\CrawlerX\Support\SizeParser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Fields of one torrent card, shared by the detail page and the list cards of the Onejav family (onejav, 141jav,
 * ffjav): cover, title link, size, date, tags, description, actresses, magnet and .torrent links.
 */
final class TorrentCardFields
{
    /**
     * @param  callable(string, string): string  $absolute  (baseUrl, href) => absolute url
     * @return array<string, mixed>
     */
    public static function read(Crawler $scope, string $baseUrl, callable $absolute): array
    {
        $size = self::text($scope, 'h5.title span.has-text-grey');
        $magnet = self::attribute($scope, 'a[href^="magnet:"]', 'href');
        $torrent = self::attribute($scope, 'a[href$=".torrent"], a[href*=".torrent?"]', 'href');
        $cover = self::attribute($scope, 'img.image', 'src');
        $dateHref = self::attribute($scope, 'p.subtitle a', 'href');
        $dateText = self::text($scope, 'p.subtitle a');
        $buttonLabel = self::text($scope, 'a[href$=".torrent"], a[href*=".torrent?"]');

        $downloadSizeLabel = $size ?? self::sizeFrom($buttonLabel);
        $downloads = [];
        if ($torrent !== null) {
            $downloads[] = array_filter([
                'type' => 'torrent',
                'url' => $absolute($baseUrl, $torrent),
                'size_label' => $downloadSizeLabel,
                'size_bytes' => SizeParser::bytes($downloadSizeLabel),
            ], static fn(mixed $value): bool => $value !== null);
        }

        return MovieMeta::filter([
            'cover_url' => $cover === null ? null : $absolute($baseUrl, $cover),
            'description' => self::text($scope, 'p.level.has-text-grey-dark, div.level.has-text-grey-dark'),
            'date' => MovieMeta::isoDate((string) $dateHref) ?? MovieMeta::isoDate((string) $dateText) ?? self::monthDate($dateText),
            'tags' => self::texts($scope, 'a.tag.is-light'),
            'performers' => self::performers($scope, $baseUrl, $absolute),
            'metadata' => MovieMeta::filter([
                'download_url' => $torrent === null ? null : $absolute($baseUrl, $torrent),
                'download_size_label' => $downloadSizeLabel,
                'download_size_bytes' => SizeParser::bytes($downloadSizeLabel),
                MovieMeta::MAGNETS => $magnet === null ? [] : [MovieMeta::magnet($magnet, null, $downloadSizeLabel)],
                MovieMeta::DOWNLOADS => $downloads,
                MovieMeta::RELEASE_DATE_RAW => $dateText,
                'old_torrent' => $scope->filter('.has-text-warning.tooltip')->count() > 0 ? true : null,
            ]),
        ]);
    }

    /**
     * @param  callable(string, string): string  $absolute
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private static function performers(Crawler $scope, string $baseUrl, callable $absolute): array
    {
        $performers = [];
        $scope->filter('a.panel-block[href*="/actress/"]')->each(static function (Crawler $link) use (&$performers, $baseUrl, $absolute): void {
            $name = trim((string) preg_replace('/\s+/u', ' ', $link->text('')));
            $href = (string) $link->attr('href');
            if ($name !== '') {
                $performers[$name] ??= MovieMeta::performer($name, null, $href === '' ? null : $absolute($baseUrl, $href));
            }
        });

        return array_values($performers);
    }

    /** "Sept. 9, 2026" / "Jan. 19, 2024" when the date link carries no numeric date. */
    private static function monthDate(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $date = date_create_immutable((string) preg_replace(['/^Sept\b\.?/', '/\.(?=\s)/'], ['Sep', ''], $text));

        return $date === false ? null : $date->format('Y-m-d');
    }

    private static function sizeFrom(?string $label): ?string
    {
        return $label !== null && preg_match('/\d+(?:[.,]\d+)?\s*(?:KB|MB|GB|TB)/i', $label, $match) === 1 ? $match[0] : null;
    }

    private static function text(Crawler $scope, string $selector): ?string
    {
        $node = $scope->filter($selector);
        $text = $node->count() > 0 ? trim((string) preg_replace('/[\s\x{a0}]+/u', ' ', $node->first()->text(''))) : '';

        return $text === '' ? null : $text;
    }

    private static function attribute(Crawler $scope, string $selector, string $attribute): ?string
    {
        $node = $scope->filter($selector);
        $value = $node->count() > 0 ? $node->first()->attr($attribute) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return list<string> */
    private static function texts(Crawler $scope, string $selector): array
    {
        $texts = $scope->filter($selector)->each(static fn(Crawler $node): string => trim((string) preg_replace('/\s+/u', ' ', $node->text(''))));

        return array_values(array_unique(array_filter($texts, static fn(string $text): bool => $text !== '')));
    }
}
