<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\OnePondo;

use JOOservices\CrawlerX\Adapters\OnePondo\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

class FieldsTest extends TestCase
{
    public function test_the_movie_json_yields_both_languages_actress_ids_and_extra_data(): void
    {
        $json = $this->loadFixture('onepondo/detail-060426_001.json');
        $client = $this->clientWithMappedResponses([
            'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json' => ['body' => $json, 'headers' => ['Content-Type' => 'application/json']],
        ]);

        $m = (new Detail($client))
            ->execute(new CrawlRequestDto(url: 'https://en.1pondo.tv/movies/060426_001/', type: CrawlType::Detail))->meta['movie'];

        self::assertSame('Hotel concierge who also handles customer harassment: Miyu Morita', $m['title']);
        self::assertSame('6358', $m['performers'][0]['external_id']);
        self::assertSame('Miyu Morita', $m['performers'][0]['name']);
        self::assertSame('森田みゆ', $m['performers'][0]['name_japanese']);

        $meta = $m['metadata'];
        self::assertSame('カスハラにも対応するホテルコンシェルジュ 森田みゆ', $meta['original_title']);
        self::assertSame('カスハラにも対応するホテルコンシェルジュ 森田みゆ', $meta['titles']['ja']);
        self::assertSame('Hotel Concierge', $meta['series']);
        self::assertSame('ホテルコンシェルジュ', $meta['series_titles']['ja']);
        self::assertSame(666, $meta['series_id']);
        self::assertSame(['score' => 5.0, 'scale' => 5.0, 'votes' => null], $meta['rating']);
        self::assertContains('AV女優', $meta['tags_ja']);
        self::assertStringEndsWith('1080p.mp4', $meta['sample_video_url']);
        self::assertSame('https://www.1pondo.tv/moviepages/060426_001/images/thum_b.jpg', $meta['thumbnail_url']);
        self::assertSame('1Pondo', $meta['maker']);
    }
}
