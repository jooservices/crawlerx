<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Shared;

use Faker\Factory;
use JOOservices\CrawlerX\Adapters\OnePondo\Site;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\FamilyCrawler;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\MovieRowFields;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\SourceFields;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnePondoFamilyCompletenessTest extends TestCase
{
    /** Public captures of family sites whose adapters are not yet registered; kept out of the manifest-checked Fixtures tree. */
    private static function pendingRoot(): string
    {
        return dirname(__DIR__, 3) . '/FamilyFixtures/';
    }

    private static function fixturePath(string $relative): string
    {
        $path = dirname(__DIR__, 3) . '/Fixtures/' . $relative;

        return is_file($path) ? $path : self::pendingRoot() . $relative;
    }

    /**
     * Scans every fixture directory whose manifest registers a family crawler, including sites merged later.
     * PacoPacoMama/MuraMura captures additionally exercise shared code before their adapter branches merge.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rows(): iterable
    {
        $registry = new FileAdapterManifestRegistry();
        $roots = [dirname(__DIR__, 3) . '/Fixtures/', self::pendingRoot()];
        foreach (array_merge(...array_map(static fn(string $root): array => glob($root . '*', GLOB_ONLYDIR) ?: [], $roots)) as $directory) {
            $slug = basename($directory);
            $manifest = $registry->get($slug);
            $registeredFamily = $manifest !== null && is_subclass_of($manifest->adapterClass, FamilyCrawler::class);
            if (! $registeredFamily && ! in_array($slug, ['pacopacomama', 'muramura'], true)) {
                continue;
            }

            $kinds = [];
            foreach (glob($directory . '/*.json') as $file) {
                if (str_ends_with($file, '.meta.json') || ! preg_match('/^(detail|list)[-.]/', basename($file), $match)) {
                    continue;
                }

                $kinds[$match[1]] = true;
                $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                foreach ($payload['Rows'] ?? [$payload] as $index => $row) {
                    yield $slug . '/' . basename($file) . ':' . $index => [$row];
                }
            }

            if (! isset($kinds['detail'], $kinds['list'])) {
                throw new \RuntimeException('Family site needs detail and list fixtures: ' . $slug);
            }
        }
    }

    /** @param array<string, mixed> $row */
    #[DataProvider('rows')]
    public function test_every_source_key_is_explicitly_mapped_or_ignored(array $row): void
    {
        $known = [...SourceFields::NORMALIZED_KEYS, ...array_keys(SourceFields::METADATA_KEYS), ...array_keys(SourceFields::IGNORED_KEYS)];
        self::assertSame([], array_values(array_diff(array_keys($row), $known)), 'Unaccounted source fields');
        self::assertCount(count(array_unique($known)), $known, 'Each source key must have exactly one inventory entry');
        foreach (SourceFields::IGNORED_KEYS as $reason) {
            self::assertNotSame('', trim($reason));
        }
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function fields(): iterable
    {
        foreach (self::rows() as $name => [$row]) {
            foreach (array_keys($row) as $field) {
                yield $name . ':' . $field => [$row, $field];
            }
        }
    }

    /** @param array<string, mixed> $row */
    #[DataProvider('fields')]
    public function test_each_real_source_field_reaches_its_documented_destination(array $row, string $field): void
    {
        $mapper = new MovieRowFields(Site::config());
        $data = $mapper->read($row);
        $meta = $data['metadata'];
        if (isset(SourceFields::METADATA_KEYS[$field])) {
            $key = SourceFields::METADATA_KEYS[$field];
            self::assertArrayHasKey($key, $meta);
            self::assertSame($row[$field], $meta[$key]);

            return;
        }

        if (isset(SourceFields::IGNORED_KEYS[$field])) {
            $without = $row;
            unset($without[$field]);
            self::assertSame($data, $mapper->read($without));

            return;
        }

        [$expected, $actual] = match ($field) {
            'MovieID' => [$row[$field], $data['code']],
            'Title' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['titles']['ja'] ?? null],
            'TitleEn' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['titles']['en'] ?? null],
            'Desc' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['descriptions']['ja'] ?? null],
            'DescEn' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['descriptions']['en'] ?? null],
            'Release' => [(new \DateTimeImmutable($row[$field]))->format('Y-m-d'), $data['date']],
            'Duration' => [max(1, (int) round($row[$field] / 60)), $data['duration']],
            'AvgRating' => [$row[$field] === null ? null : (float) $row[$field], $meta['rating']['score'] ?? null],
            'UC' => [$row[$field], $meta['tag_ids'] ?? []],
            'UCNAME' => [$row[$field], $meta['tags_ja'] ?? []],
            'UCNAMEEn' => [$row[$field], $data['tags'] ?? []],
            'Series' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['series_titles']['ja'] ?? null],
            'SeriesEn' => [is_string($row[$field]) && trim($row[$field]) !== '' ? trim($row[$field]) : null, $meta['series_titles']['en'] ?? null],
            'SeriesID' => [$row[$field], $meta['series_id'] ?? null],
            'MovieThumb' => [$row[$field], $meta['thumbnail_url']],
            'ThumbUltra' => [$row[$field], $meta['stream_cover_url']],
            'Year' => [$row[$field], $meta['movie_year']],
            'MetaMovieID' => [$row[$field], $meta['meta_movie_id']],
            'CanStream' => [$row[$field], $meta['can_stream']],
            'HasGallery' => [$row[$field], $meta['has_gallery']],
            default => throw new \RuntimeException('Source field lacks an assertion: ' . $field),
        };
        self::assertSame($expected, $actual, $field);
    }

    public function test_existing_dto_keys_and_values_remain_byte_identical(): void
    {
        // Captured from the unmodified develop mapper (8042c36), using the same shared config for every source.
        $baselines = json_decode(file_get_contents(self::pendingRoot() . 'onepondo-family-legacy-hashes.json'), true, 512, JSON_THROW_ON_ERROR);
        $legacyKeys = ['maker', 'series', 'series_titles', 'series_id', 'titles', 'original_title', 'descriptions', 'rating', 'tags_ja', 'tag_ids', 'sample_streams', 'sample_video_url', 'thumbnail_url', 'stream_cover_url', 'movie_year', 'meta_movie_id', 'can_stream', 'has_gallery'];
        $mapper = new MovieRowFields(Site::config());
        foreach ($baselines as $fixture => $hashes) {
            $payload = json_decode(file_get_contents(self::fixturePath($fixture)), true, 512, JSON_THROW_ON_ERROR);
            $actual = [];
            foreach ($payload['Rows'] ?? [$payload] as $row) {
                $data = $mapper->read($row);
                $data['metadata'] = array_intersect_key($data['metadata'], array_flip($legacyKeys));
                $movie = MovieDto::fromParsed($row['MovieID'], $mapper->title($row), $data)->toArray();
                $actual[] = hash('sha256', json_encode($movie, JSON_THROW_ON_ERROR));
            }
            self::assertSame($hashes, $actual, $fixture);
        }
    }

    public function test_missing_fields_are_not_invented_and_present_empty_values_are_preserved(): void
    {
        self::assertSame([], SourceFields::metadata([]));
        foreach (SourceFields::METADATA_KEYS as $source => $destination) {
            foreach ([null, false, 0, '', []] as $value) {
                self::assertSame([$destination => $source === 'Expire' ? null : $value], SourceFields::metadata([$source => $value]));
            }
        }
    }

    public function test_expiry_dates_are_iso_without_changing_other_source_values(): void
    {
        $date = Factory::create()->dateTime();
        self::assertSame(['expires_at' => $date->format('Y-m-d')], SourceFields::metadata(['Expire' => $date->format('Y/m/d')]));
        self::assertSame(['expires_at' => null], SourceFields::metadata(['Expire' => 'invalid']));
    }

}
