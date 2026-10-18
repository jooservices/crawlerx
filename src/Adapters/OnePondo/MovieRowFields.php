<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\OnePondo;

use JOOservices\CrawlerX\Support\MovieMeta;

/**
 * Maps one 1Pondo movie record to movie data. The detail JSON and every row of the list JSON carry the same record
 * shape, so the detail page and the list cards return the same fields.
 */
final class MovieRowFields
{
    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function read(array $detail): array
    {
        return MovieMeta::filter([
            'code' => $this->stringField($detail, 'MovieID'),
            'cover_url' => $this->coverUrl($detail),
            'description' => $this->description($detail),
            'date' => $this->releaseDate($detail),
            'duration' => $this->durationMinutes($detail),
            'performers' => $this->performerEntries($detail),
            'tags' => $this->tags($detail),
            'metadata' => MovieMeta::filter([
                MovieMeta::MAKER => '1Pondo',
                MovieMeta::SERIES => $this->stringField($detail, 'SeriesEn') ?? $this->stringField($detail, 'Series'),
                'series_titles' => $this->pair($detail, 'Series', 'SeriesEn'),
                'series_id' => $detail['SeriesID'] ?? null,
                MovieMeta::TITLES => $this->pair($detail, 'Title', 'TitleEn'),
                MovieMeta::ORIGINAL_TITLE => $this->stringField($detail, 'Title'),
                'descriptions' => $this->pair($detail, 'Desc', 'DescEn'),
                MovieMeta::RATING => is_numeric($detail['AvgRating'] ?? null) ? MovieMeta::rating((float) $detail['AvgRating'], 5.0) : null,
                'tags_ja' => $this->stringList($detail['UCNAME'] ?? []),
                'tag_ids' => array_values(array_filter((array) ($detail['UC'] ?? []), 'is_int')),
                'sample_streams' => $this->sampleStreams($detail),
                MovieMeta::SAMPLE_VIDEO_URL => $this->bestSample($detail),
                'thumbnail_url' => $this->stringField($detail, 'MovieThumb'),
                'stream_cover_url' => $this->stringField($detail, 'ThumbUltra'),
                'movie_year' => $this->stringField($detail, 'Year'),
                'meta_movie_id' => $detail['MetaMovieID'] ?? null,
                'can_stream' => $detail['CanStream'] ?? null,
                'has_gallery' => $detail['HasGallery'] ?? null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    public function title(array $detail): ?string
    {
        return $this->stringField($detail, 'TitleEn') ?? $this->stringField($detail, 'Title');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function description(array $detail): ?string
    {
        return $this->stringField($detail, 'DescEn') ?? $this->stringField($detail, 'Desc');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function coverUrl(array $detail): ?string
    {
        return $this->stringField($detail, 'ThumbHigh')
            ?? $this->stringField($detail, 'MovieThumb')
            ?? $this->stringField($detail, 'ThumbMed');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function releaseDate(array $detail): ?string
    {
        $value = $this->stringField($detail, 'Release');
        if ($value === null) {
            return null;
        }

        $date = date_create_immutable($value);

        return $date === false ? null : $date->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function durationMinutes(array $detail): ?int
    {
        $seconds = $detail['Duration'] ?? null;
        if (! is_numeric($seconds) || (int) $seconds <= 0) {
            return null;
        }

        return max(1, (int) round(((int) $seconds) / 60));
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, string>
     */
    private function pair(array $detail, string $jaKey, string $enKey): array
    {
        return array_filter([
            'ja' => $this->stringField($detail, $jaKey),
            'en' => $this->stringField($detail, $enKey),
        ], static fn(?string $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    private function performerEntries(array $detail): array
    {
        $performers = [];
        $list = is_array($detail['ActressesList'] ?? null) ? $detail['ActressesList'] : [];
        foreach ($list as $id => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = is_string($row['NameEn'] ?? null) && $row['NameEn'] !== '' ? $row['NameEn'] : ($row['NameJa'] ?? null);
            if (! is_string($name) || $name === '') {
                continue;
            }

            $performer = MovieMeta::performer($name, (string) $id, null);
            $performer['name_japanese'] = is_string($row['NameJa'] ?? null) ? $row['NameJa'] : null;
            $performer['size_raw'] = is_string($row['Sizes'] ?? null) && $row['Sizes'] !== '0-0-0' ? $row['Sizes'] : null;
            $performer['age'] = is_numeric($row['Age'] ?? null) && (int) $row['Age'] > 0 ? (int) $row['Age'] : null;
            $performers[] = $performer;
        }

        if ($performers !== []) {
            return $performers;
        }

        return array_map(static fn(string $name): array => MovieMeta::performer($name), $this->performers($detail));
    }

    /** @param array<string, mixed> $detail */
    private function bestSample(array $detail): ?string
    {
        $streams = $this->sampleStreams($detail);

        return $streams === [] ? null : $streams[array_key_last($streams)]['url'];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function performers(array $detail): array
    {
        return $this->stringList($detail['ActressesEn'] ?? $detail['ActressesJa'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function tags(array $detail): array
    {
        return $this->stringList($detail['UCNAMEEn'] ?? $detail['UCNAME'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<array{quality: string, url: string}>
     */
    private function sampleStreams(array $detail): array
    {
        $files = $detail['SampleFiles'] ?? null;
        if (! is_array($files)) {
            return [];
        }

        $streams = [];
        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $url = $file['URL'] ?? null;
            $quality = $file['FileName'] ?? null;
            if (! is_string($url) || trim($url) === '' || ! is_string($quality) || trim($quality) === '') {
                continue;
            }

            $streams[] = [
                'quality' => trim($quality),
                'url' => trim($url),
            ];
        }

        return $streams;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn(mixed $entry): string => is_string($entry) ? trim($entry) : '', $value),
            fn(string $entry): bool => $entry !== '',
        )));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringField(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
