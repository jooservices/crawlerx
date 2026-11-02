<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Muramura;

use JOOservices\CrawlerX\Adapters\Muramura\Selectors;
use JOOservices\CrawlerX\Adapters\Muramura\Site;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\MovieRowFields;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\OnePondoFamily\UrlNormalizer;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    private const LIST_URL = 'https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_0.json';

    private const DETAIL_URL = 'https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/103126_1273.json';

    private const GALLERY_URL = 'https://www.muramura.tv/dyn/phpauto/movie_galleries/movie_id/103126_1273.json';

    public function test_listing_maps_the_live_movie_row_and_pagination(): void
    {
        $config = Site::config();
        $client = $this->clientWithMappedResponses([
            self::LIST_URL => [
                'body' => $this->loadFixture('muramura/list-newest-0.json'),
                'headers' => ['Content-Type' => 'application/json'],
            ],
        ]);

        $result = (new Listing($client, new UrlNormalizer($config), new MovieRowFields($config), $config))
            ->execute(new CrawlRequestDto(url: 'https://www.muramura.tv/list/?o=n', type: CrawlType::Listing));

        self::assertCount(1, $result->items);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(35, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);

        $movie = $result->items[0]->meta['movie'];
        self::assertSame('103126_1273', $movie['external_id']);
        self::assertSame('103126_1273', $movie['code']);
        self::assertSame('Wireless Rotor Walk : Ayaka Inoue', $movie['title']);
        self::assertSame(
            'https://www.muramura.tv/moviepages/103126_1273/images/str.jpg',
            $movie['cover_url'],
        );
        self::assertSame('2026-10-31', $movie['date']);
        self::assertSame(61, $movie['duration']);
        self::assertSame('Ayaka Inoue', $movie['performers'][0]['name']);
        self::assertSame('井上あやか', $movie['performers'][0]['name_japanese']);
        self::assertSame('Muramura', $movie['metadata']['maker']);
        self::assertFalse($movie['metadata']['has_gallery']);
        self::assertStringEndsWith('1080p.mp4', $movie['metadata']['sample_video_url']);
    }

    public function test_detail_maps_every_catalog_field_to_movie_dto_keys(): void
    {
        $config = Site::config();
        $rawDetail = json_decode(
            $this->loadFixture('muramura/detail-103126_1273.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $client = $this->clientWithMappedResponses([
            self::DETAIL_URL => [
                'body' => $this->loadFixture('muramura/detail-103126_1273.json'),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            self::GALLERY_URL => [
                'body' => '<!doctype html><title>Not Found</title>',
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html'],
            ],
        ]);

        $result = (new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config))
            ->execute(new CrawlRequestDto(
                url: 'https://www.muramura.tv/movies/103126_1273/',
                type: CrawlType::Detail,
            ));

        $movie = $result->meta['movie'];
        self::assertSame('103126_1273', $movie['external_id']);
        self::assertSame('103126_1273', $movie['code']);
        self::assertSame('Wireless Rotor Walk : Ayaka Inoue', $movie['title']);
        self::assertSame('飛びっこ散歩 ~ゴールまでたどり着ければ賞品ゲット~', $movie['metadata']['original_title']);
        self::assertSame('2026-10-31', $movie['date']);
        self::assertSame(61, $movie['duration']);
        self::assertSame($rawDetail['Desc'], $movie['metadata']['descriptions']['ja']);
        self::assertSame('Ayaka Inoue', $movie['performers'][0]['name']);
        self::assertSame('井上あやか', $movie['performers'][0]['name_japanese']);
        self::assertSame('9038', $movie['performers'][0]['external_id']);
        self::assertSame('Amateur', $movie['tags'][0]);
        self::assertSame('素人', $movie['metadata']['tags_ja'][0]);
        self::assertSame('Muramura', $movie['metadata']['maker']);
        self::assertSame('Wireless Rotor Walk : Ayaka Inoue', $movie['metadata']['titles']['en']);
        self::assertSame('飛びっこ散歩 ~ゴールまでたどり着ければ賞品ゲット~', $movie['metadata']['titles']['ja']);
        self::assertSame(11, count($movie['metadata']['tag_ids']));
        self::assertCount(5, $movie['metadata']['sample_streams']);
        self::assertSame(
            ['240p.mp4', '360p.mp4', '480p.mp4', '720p.mp4', '1080p.mp4'],
            array_column($movie['metadata']['sample_streams'], 'quality'),
        );
        self::assertSame('1080p.mp4', $movie['metadata']['sample_streams'][4]['quality']);
        self::assertSame(
            'https://smovie.muramura.tv/sample/movies/103126_1273/1080p.mp4',
            $movie['metadata']['sample_video_url'],
        );
        self::assertSame(
            'https://www.muramura.tv/moviepages/103126_1273/images/list_s_t.jpg',
            $movie['metadata']['thumbnail_url'],
        );
        self::assertSame(
            'https://www.muramura.tv/moviepages/103126_1273/images/str.jpg',
            $movie['metadata']['stream_cover_url'],
        );
        self::assertSame('2026', $movie['metadata']['movie_year']);
        self::assertSame(35673, $movie['metadata']['meta_movie_id']);
        self::assertTrue($movie['metadata']['can_stream']);
        self::assertFalse($movie['metadata']['has_gallery']);
        self::assertSame([], $movie['screenshots']);
        self::assertArrayNotHasKey('series', $movie['metadata']);
        self::assertArrayNotHasKey('member_files', $movie['metadata']);
        self::assertArrayNotHasKey('rating', $movie['metadata']);
        self::assertArrayNotHasKey('price', $movie['metadata']);
    }

    public function test_gallery_boolean_uses_the_muramura_field(): void
    {
        $config = Site::config();
        $payload = json_decode($this->loadFixture('muramura/detail-103126_1273.json'), true, flags: JSON_THROW_ON_ERROR);
        $payload['Gallery'] = true;
        $payload['HasGallery'] = false;
        $client = $this->clientWithMappedResponses([
            self::DETAIL_URL => [
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            self::GALLERY_URL => [
                'body' => '',
                'status' => 404,
            ],
        ]);

        $result = (new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config))
            ->execute(new CrawlRequestDto(
                url: 'https://www.muramura.tv/movies/103126_1273/',
                type: CrawlType::Detail,
            ));

        self::assertTrue($result->meta['movie']['metadata']['has_gallery']);
    }

    public function test_false_muramura_gallery_flag_overrides_true_default_field(): void
    {
        $config = Site::config();
        $payload = json_decode($this->loadFixture('muramura/detail-103126_1273.json'), true, flags: JSON_THROW_ON_ERROR);
        $payload['Gallery'] = false;
        $payload['HasGallery'] = true;
        $client = $this->clientWithMappedResponses([
            self::DETAIL_URL => [
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            self::GALLERY_URL => [
                'body' => '',
                'status' => 404,
            ],
        ]);

        $result = (new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config))
            ->execute(new CrawlRequestDto(
                url: 'https://www.muramura.tv/movies/103126_1273/',
                type: CrawlType::Detail,
            ));

        self::assertFalse($result->meta['movie']['metadata']['has_gallery']);
    }

    public function test_detail_preserves_movie_ids_with_two_digit_suffixes(): void
    {
        $config = Site::config();
        $payload = json_decode($this->loadFixture('muramura/detail-103126_1273.json'), true, flags: JSON_THROW_ON_ERROR);
        $payload['MovieID'] = '110226_01';
        $url = 'https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/110226_01.json';
        $galleryUrl = 'https://www.muramura.tv/dyn/phpauto/movie_galleries/movie_id/110226_01.json';
        $client = $this->clientWithMappedResponses([
            $url => [
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                'headers' => ['Content-Type' => 'application/json'],
            ],
            $galleryUrl => [
                'body' => '',
                'status' => 404,
            ],
        ]);

        $result = (new Detail($client, new UrlNormalizer($config), new MovieRowFields($config), $config))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertSame('110226_01', $result->meta['movie']['external_id']);
        self::assertSame('110226_01', $result->meta['movie']['code']);
        self::assertSame(1, preg_match(Selectors::MOVIE_ID_PATTERN, '110226_01', $matches));
        self::assertSame('110226_01', $matches[1]);
    }
}
