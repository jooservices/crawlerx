<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

use JOOservices\CrawlerX\Dto\Entity\PhotoDto;

/**
 * Builds PhotoDto objects from the `imageinfo` of a Commons file page.
 */
final class Photos
{
    /**
     * @param  array<string, mixed>  $page
     */
    public static function fromPage(array $page, int $position): ?PhotoDto
    {
        $infos = is_array($page['imageinfo'] ?? null) ? $page['imageinfo'] : [];
        $info = CommonsApi::assoc($infos[0] ?? []);
        $original = is_string($info['url'] ?? null) ? Site::cleanMediaUrl($info['url']) : null;
        if ($original === null || ! is_int($page['pageid'] ?? null)) {
            return null;
        }

        $thumbnail = is_string($info['thumburl'] ?? null) ? Site::cleanMediaUrl($info['thumburl']) : null;
        $descriptionUrl = is_string($info['descriptionurl'] ?? null) ? $info['descriptionurl'] : ($page['fullurl'] ?? null);

        return new PhotoDto(
            id: (string) $page['pageid'],
            url: is_string($descriptionUrl) ? $descriptionUrl : null,
            imageUrl: $original,
            thumbnailUrl: $thumbnail,
            position: $position,
            metadata: self::metadata($page, $info),
        );
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private static function metadata(array $page, array $info): array
    {
        $ext = self::extmetadata($info);
        $raw = self::rawFields($ext);
        $user = is_string($info['user'] ?? null) ? $info['user'] : null;
        $duration = is_numeric($info['duration'] ?? null) ? (float) $info['duration'] : null;

        return array_filter([
            'title' => $info['canonicaltitle'] ?? $page['title'] ?? null,
            'name' => Markup::text($ext['ObjectName'] ?? null),
            'description' => Markup::text($ext['ImageDescription'] ?? null),
            'author' => Markup::text($ext['Artist'] ?? null),
            'author_url' => Markup::firstLink($ext['Artist'] ?? null),
            'credit' => Markup::text($ext['Credit'] ?? null),
            'credit_url' => Markup::firstLink($ext['Credit'] ?? null),
            'license' => Markup::text($ext['LicenseShortName'] ?? null),
            'license_url' => Markup::text($ext['LicenseUrl'] ?? null),
            'license_code' => Markup::text($ext['License'] ?? null),
            'usage_terms' => Markup::text($ext['UsageTerms'] ?? null),
            'attribution_required' => self::flag($ext['AttributionRequired'] ?? null),
            'copyrighted' => self::flag($ext['Copyrighted'] ?? null),
            'restrictions' => Markup::text($ext['Restrictions'] ?? null),
            'permission' => Markup::text($ext['Permission'] ?? null),
            'date' => Markup::isoDate($ext['DateTimeOriginal'] ?? null),
            'date_modified' => Markup::isoDate($ext['DateTime'] ?? null),
            'uploaded_at' => Markup::isoTimestamp($info['timestamp'] ?? null),
            'uploader' => $user,
            'uploader_url' => $user === null ? null : Site::pageUrl('User:' . $user),
            'uploader_id' => $info['userid'] ?? null,
            'upload_comment' => Markup::text($info['parsedcomment'] ?? $info['comment'] ?? null),
            'width' => $info['width'] ?? null,
            'height' => $info['height'] ?? null,
            'size' => $info['size'] ?? null,
            'mime' => $info['mime'] ?? null,
            'media_type' => $info['mediatype'] ?? null,
            'bit_depth' => $info['bitdepth'] ?? null,
            'duration_seconds' => $duration,
            'duration_minutes' => $duration === null ? null : round($duration / 60, 2),
            'sha1' => $info['sha1'] ?? null,
            'thumbnail_width' => $info['thumbwidth'] ?? null,
            'thumbnail_height' => $info['thumbheight'] ?? null,
            'short_url' => $info['descriptionshorturl'] ?? null,
            'latitude' => self::coordinate($ext['GPSLatitude'] ?? null),
            'longitude' => self::coordinate($ext['GPSLongitude'] ?? null),
            'tags' => self::tags($ext),
            'categories' => self::categories($page),
            'assessments' => Markup::text($ext['Assessments'] ?? null),
            'source_fields' => $raw,
        ], static fn(mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private static function extmetadata(array $info): array
    {
        $values = [];
        foreach (CommonsApi::assoc($info['extmetadata'] ?? []) as $key => $entry) {
            $values[$key] = CommonsApi::assoc($entry)['value'] ?? null;
        }

        return $values;
    }

    /**
     * Raw labelled rows (extmetadata key -> plain value) exactly as the file page lists them.
     *
     * @param  array<string, mixed>  $ext
     * @return array<string, string>
     */
    private static function rawFields(array $ext): array
    {
        $rows = [];
        foreach ($ext as $key => $value) {
            $text = is_scalar($value) ? Markup::text((string) $value) : null;
            if ($text !== null) {
                $rows[$key] = $text;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $ext
     * @return list<string>
     */
    private static function tags(array $ext): array
    {
        $categories = $ext['Categories'] ?? null;

        return is_string($categories) && $categories !== ''
            ? array_values(array_filter(explode('|', $categories), static fn(string $tag): bool => $tag !== ''))
            : [];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<string>
     */
    public static function categories(array $page): array
    {
        $names = [];
        foreach (is_array($page['categories'] ?? null) ? $page['categories'] : [] as $category) {
            $title = CommonsApi::assoc($category)['title'] ?? null;
            if (is_string($title)) {
                $names[] = preg_replace('/^Category:/', '', $title) ?? $title;
            }
        }

        return $names;
    }

    private static function flag(mixed $value): ?bool
    {
        return match (is_string($value) ? strtolower($value) : null) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private static function coordinate(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
