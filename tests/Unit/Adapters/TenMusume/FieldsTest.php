<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\TenMusume;

use JOOservices\CrawlerX\Adapters\TenMusume\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_movie_json_yields_both_languages_actress_ids_and_extra_data(): void
    {
        $json = $this->loadFixture('10musume/detail-110326_01.json');
        $client = $this->clientWithMappedResponses([
            'https://www.10musume.com/dyn/phpauto/movie_details/movie_id/110326_01.json' => ['body' => $json, 'headers' => ['Content-Type' => 'application/json']],
        ]);

        $m = (new Detail($client))
            ->execute(new CrawlRequestDto(url: 'https://www.10musume.com/movies/110326_01/', type: CrawlType::Detail))->meta['movie'];

        self::assertSame('110326_01', $m['external_id']);
        self::assertSame('Pussy to Mouth Sex: Saki Konno', $m['title']);
        self::assertSame('8983', $m['performers'][0]['external_id']);
        self::assertSame('Saki Konno', $m['performers'][0]['name']);
        self::assertSame('紺野咲', $m['performers'][0]['name_japanese']);
        self::assertSame(61, $m['duration']);

        $meta = $m['metadata'];
        self::assertSame('愛液を溜めてきたご無沙汰ガールと中出しPtoMセックス', $meta['original_title']);
        self::assertSame('愛液を溜めてきたご無沙汰ガールと中出しPtoMセックス', $meta['titles']['ja']);
        self::assertSame('Pussy to Mouth Sex', $meta['series']);
        self::assertSame('中出しPtoMセックス！', $meta['series_titles']['ja']);
        self::assertSame(675, $meta['series_id']);
        self::assertContains('Amateur', $m['tags']);
        self::assertContains('素人', $meta['tags_ja']);
        self::assertStringEndsWith('1080p.mp4', $meta['sample_video_url']);
        self::assertSame('https://www.10musume.com/moviepages/110326_01/images/list1.jpg', $meta['thumbnail_url']);
        self::assertSame('10Musume', $meta['maker']);
    }

    public function test_the_gallery_json_yields_public_sample_screenshots_only(): void
    {
        $detail = $this->loadFixture('10musume/detail-110326_01.json');
        $gallery = json_encode([
            'MovieID' => '110326_01',
            'Rows' => [
                ['Img' => 'movie_gallery/sample/110326_01/f1a0d169d4b604a0d73d9c422b05e12a.jpg', 'Protected' => false],
                ['Img' => 'movie_gallery/sample/110326_01/5de7fab93bc4e79975c188809c91c215.jpg', 'Protected' => false],
                ['Img' => 'movie_gallery/member/110326_01/31be409e1766cd494fb1acc9fd231954.jpg', 'Protected' => true],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = $this->clientWithMappedResponses([
            'https://www.10musume.com/dyn/phpauto/movie_details/movie_id/110326_01.json' => ['body' => $detail, 'headers' => ['Content-Type' => 'application/json']],
            'https://www.10musume.com/dyn/dla/json/movie_gallery/110326_01.json' => ['body' => $gallery, 'headers' => ['Content-Type' => 'application/json']],
        ]);

        $m = (new Detail($client))
            ->execute(new CrawlRequestDto(url: 'https://www.10musume.com/movies/110326_01/', type: CrawlType::Detail))->meta['movie'];

        self::assertCount(2, $m['screenshots']);
        self::assertSame(
            'https://www.10musume.com/dyn/dla/images/movie_gallery/sample/110326_01/f1a0d169d4b604a0d73d9c422b05e12a.jpg',
            $m['screenshots'][0]['url'],
        );
        self::assertSame(
            'https://www.10musume.com/dyn/dla/images/movie_gallery/sample/110326_01/5de7fab93bc4e79975c188809c91c215.jpg',
            $m['screenshots'][1]['url'],
        );
    }
}
