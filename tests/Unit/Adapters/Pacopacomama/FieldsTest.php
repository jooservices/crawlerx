<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Pacopacomama;

use JOOservices\CrawlerX\Adapters\Pacopacomama\PacopacomamaCrawler;
use JOOservices\CrawlerX\Adapters\Pacopacomama\Site;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\MovieRowFields;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\UrlNormalizer;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const BASE = 'https://www.pacopacomama.com';

    public function test_detail_maps_public_catalog_fields_and_filters_member_images(): void
    {
        $config = Site::config();
        $client = $this->clientWithMappedResponses([
            self::BASE . '/dyn/phpauto/movie_details/movie_id/110326_100.json' => ['body' => $this->loadFixture('pacopacomama/detail-110326_100.json')],
            self::BASE . '/dyn/dla/json/movie_gallery/110326_100.json' => ['body' => $this->loadFixture('pacopacomama/gallery-110326_100.json')],
        ]);
        $type = new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config);
        $public = $type->execute(new CrawlRequestDto(url: self::BASE . '/movies/110326_100/'));
        $api = $type->execute(new CrawlRequestDto(url: self::BASE . '/dyn/phpauto/movie_details/movie_id/110326_100.json'));
        self::assertSame($public->toArray(), $api->toArray());
        $movie = $public->meta['movie'];
        $raw = json_decode($this->loadFixture('pacopacomama/detail-110326_100.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($raw['MovieID'], $movie['external_id']);
        self::assertSame($raw['MovieID'], $movie['code']);
        self::assertSame($raw['TitleEn'], $movie['title']);
        self::assertSame(trim($raw['Desc']), $movie['description']);
        self::assertSame('2026-11-03', $movie['date']);
        self::assertSame(55, $movie['duration']);
        self::assertSame($raw['ThumbHigh'], $movie['cover_url']);
        self::assertSame($raw['UCNAMEEn'], $movie['tags']);
        self::assertSame('8921', $movie['performers'][0]['external_id']);
        self::assertSame('Momoko Akasaka', $movie['performers'][0]['name']);
        self::assertSame($raw['Actor'], $movie['performers'][0]['name_japanese']);
        self::assertNull($movie['performers'][0]['metadata']['age']);
        self::assertNull($movie['performers'][0]['size_raw']);
        $meta = $movie['metadata'];
        self::assertSame('Pacopacomama', $meta['maker']);
        self::assertSame(['ja' => trim($raw['Title']), 'en' => $raw['TitleEn']], $meta['titles']);
        self::assertSame(trim($raw['Title']), $meta['original_title']);
        self::assertSame(['ja' => trim($raw['Desc'])], $meta['descriptions']);
        self::assertSame($raw['SeriesEn'], $meta['series']);
        self::assertSame(['ja' => $raw['Series'], 'en' => $raw['SeriesEn']], $meta['series_titles']);
        self::assertSame($raw['SeriesID'], $meta['series_id']);
        self::assertSame($raw['UCNAME'], $meta['tags_ja']);
        self::assertSame($raw['UC'], $meta['tag_ids']);
        self::assertSame($raw['Year'], $meta['movie_year']);
        self::assertSame($raw['MetaMovieID'], $meta['meta_movie_id']);
        self::assertTrue($meta['can_stream']);
        self::assertFalse($meta['has_gallery']);
        self::assertSame($raw['MovieThumb'], $meta['thumbnail_url']);
        self::assertSame($raw['ThumbUltra'], $meta['stream_cover_url']);
        self::assertArrayNotHasKey('rating', $meta);
        self::assertSame(array_map(static fn(array $file): array => ['quality' => $file['FileName'], 'url' => $file['URL']], $raw['SampleFiles']), $meta['sample_streams']);
        self::assertSame($raw['SampleFiles'][4]['URL'], $meta['sample_video_url']);
        self::assertArrayNotHasKey('MemberFiles', $meta);
        self::assertCount(3, $movie['screenshots']);
        foreach ($movie['screenshots'] as $index => $image) {
            self::assertSame($index + 1, $image['metadata']['position']);
            self::assertStringStartsWith(self::BASE . '/dyn/dla/images/movie_gallery/sample/110326_100/', $image['url']);
            self::assertSame('pacopacomama_gallery', $image['metadata']['metadata']['source']);
        }
    }

    public function test_pagination_uses_offsets_and_derives_last_page_from_total_rows(): void
    {
        $config = Site::config();
        $json = $this->loadFixture('pacopacomama/list-newest-0.json');
        $nextPage = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $nextPage['Rows'] = [$nextPage['Rows'][1]];
        $client = $this->clientWithMappedResponses([
            self::BASE . '/dyn/phpauto/movie_lists/list_newest_0.json' => ['body' => $json],
            self::BASE . '/dyn/phpauto/movie_lists/list_newest_50.json' => ['body' => json_encode($nextPage, JSON_THROW_ON_ERROR)],
        ]);
        $type = new Listing($client, new UrlNormalizer($config), new MovieRowFields($config), $config);
        $page = $type->execute(new CrawlRequestDto(url: self::BASE . '/list/?o=n', page: 2));
        self::assertSame(2, $page->pagination->currentPage);
        self::assertSame(63, $page->pagination->lastPage);
        self::assertSame(3, $page->pagination->nextPage);
        self::assertSame(self::BASE . '/list/?o=n&page=3', $page->pagination->nextUrl);
        self::assertTrue($page->pagination->hasNextPage);
        self::assertCount(1, $page->items);
        self::assertSame($nextPage['Rows'][0]['MovieID'], $page->items[0]->meta['movie']['external_id']);
        self::assertSame(self::BASE . '/movies/' . $nextPage['Rows'][0]['MovieID'] . '/', $page->items[0]->url);
        self::assertArrayNotHasKey('totalRows', $page->pagination->toArray());
    }

    public function test_secondary_gallery_capture_has_verified_provenance(): void
    {
        $path = $this->fixturePath('pacopacomama/gallery-110326_100.json');
        $metadata = json_decode($this->loadFixture('pacopacomama/gallery-110326_100.json.meta.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(hash_file('sha256', $path), $metadata['content_hash']);
        self::assertSame(self::BASE . '/dyn/dla/json/movie_gallery/110326_100.json', $metadata['source_url']);
        self::assertSame(200, $metadata['http_status']);
        self::assertFalse($metadata['sanitized']);
    }

    public function test_two_digit_movie_id_and_optional_gallery(): void
    {
        $config = Site::config();
        $id = '110226_01';
        $client = $this->clientWithMappedResponses([
            self::BASE . '/dyn/phpauto/movie_details/movie_id/' . $id . '.json' => ['body' => $this->loadFixture('pacopacomama/detail-110326_100.json')],
        ]);
        $type = new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config);
        $result = $type->execute(new CrawlRequestDto(url: self::BASE . '/dyn/phpauto/movie_details/movie_id/' . $id . '.json'));
        self::assertSame($id, $result->meta['movie']['external_id']);
        self::assertSame([], $result->meta['movie']['screenshots']);
    }

    public function test_manifest_registration_and_shared_url_detection(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $adapter = $registry->resolve('pacopacomama');
        self::assertInstanceOf(PacopacomamaCrawler::class, $adapter);
        self::assertSame('pacopacomama', $adapter->name());
        foreach (['/movies/110226_01/', '/movies/110326_100/', '/dyn/phpauto/movie_details/movie_id/110326_100.json'] as $path) {
            self::assertSame(CrawlType::Detail, $adapter->detectUrl(self::BASE . $path)->crawlType);
        }
        foreach (['/list/?o=n', '/dyn/phpauto/movie_lists/list_newest_50.json'] as $path) {
            self::assertSame(CrawlType::Listing, $adapter->detectUrl(self::BASE . $path)->crawlType);
        }
        self::assertFalse($adapter->detectUrl('https://unrelated.example/movies/110326_100/')->hostMatched);
    }
}
