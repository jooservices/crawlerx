<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Adapters\OnePondo\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;
use RuntimeException;

final class OnePondoDetailTest extends TestCase
{
    public function test_detail_parses_metadata_screenshots_and_sample_streams(): void
    {
        $detail = $this->loadFixture('onepondo/detail-060426_001.json');
        $gallery = $this->loadFixture('onepondo/gallery-060426_001.json');
        $url = 'https://en.1pondo.tv/movies/060426_001/';

        $client = $this->clientWithMappedResponses([
            'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json' => ['body' => $detail],
            'https://en.1pondo.tv/dyn/dla/json/movie_gallery/060426_001.json' => ['body' => $gallery],
        ]);

        $result = (new Detail($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Detail));

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('060426_001', $result->meta['movie']['code']);
        self::assertSame('2026-06-04', $result->meta['movie']['date']);
        self::assertSame(61, $result->meta['movie']['duration']);
        self::assertSame('1Pondo', $result->meta['movie']['metadata']['maker']);
        self::assertSame('Miyu Morita', $result->meta['movie']['performers'][0]['name']);
        self::assertContains('Creampie', $result->meta['movie']['tags']);
        self::assertStringContainsString('moviepages/060426_001/images/str.jpg', (string) $result->meta['movie']['cover_url']);
        self::assertNotEmpty($result->meta['movie']['screenshots']);
        self::assertStringContainsString(
            'dyn/dla/images/movie_gallery/sample/060426_001/',
            (string) $result->meta['movie']['screenshots'][0]['url'],
        );
        self::assertNotEmpty($result->meta['movie']['metadata']['sample_streams']);
    }

    public function test_detail_falls_back_when_gallery_missing(): void
    {
        $detail = $this->loadFixture('onepondo/detail-060426_001.json');
        $detailPayload = json_decode($detail, true);
        assert(is_array($detailPayload));
        unset($detailPayload['DescEn']);
        $detail = (string) json_encode($detailPayload, JSON_THROW_ON_ERROR);

        $client = $this->clientWithMappedResponses([
            'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json' => ['body' => $detail],
            'https://en.1pondo.tv/dyn/dla/json/movie_gallery/060426_001.json' => ['body' => '', 'status' => 404],
        ]);

        $result = (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/movies/060426_001/',
            type: CrawlType::Detail,
        ));

        self::assertSame([], $result->meta['movie']['screenshots']);
        self::assertIsString($result->meta['movie']['description']);
        self::assertNotSame('', $result->meta['movie']['description']);
    }

    public function test_detail_throws_on_invalid_json(): void
    {
        $client = $this->clientWithHtml('not-json');

        $this->expectException(RuntimeException::class);

        (new Detail($client))->execute(new CrawlRequestDto(
            url: 'https://en.1pondo.tv/movies/060426_001/',
            type: CrawlType::Detail,
        ));
    }
}
