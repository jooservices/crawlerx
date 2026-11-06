<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Opera;

use JOOservices\CrawlerX\Adapters\Opera\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class OperaTest extends TestCase
{
    private const BASE = 'https://av-opera.jp';

    private const COVER = 'https://cdn.up-timely.com/image/19/content/69865/HQl6RJmwKn1UO4UT9PAUs7zGBAJjG7QXVhQdGsfD.jpg';

    private const TITLE = 'OPERA　2021年下半期　2022年一年分　全作品BEST　12作品4時間';

    public function test_detail_fields_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('opera/detail-OPBD209.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/OPBD209'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/OPBD209', $result->url);
        self::assertSame('OPBD209', $movie['external_id']);
        self::assertSame(self::TITLE, $movie['title']);
        self::assertSame('OPBD209', $movie['code']);
        self::assertSame(self::COVER, $movie['cover_url']);
        self::assertSame('2021年下半期＆2022年一年分の作品をセットにした超豪華スカトロBEST。本物双子のレズスカ、サックス奏者の豪快大便、人気シリーズの糞豚、可愛い素人さんのウンコetc、盛り沢山のウンコ作品を詰め込みました！！', $movie['description']);
        self::assertSame('2023-08-22', $movie['date']);
        self::assertSame(240, $movie['duration']);
        self::assertSame(['768169', '812985', '812986'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['白沢比呂', '白石のん', '白石らん'], array_column($movie['performers'], 'name'));
        self::assertSame(['白沢比呂', '白石のん', '白石らん'], array_column($movie['performers'], 'name_japanese'));
        self::assertSame([self::BASE . '/actress/detail/768169', self::BASE . '/actress/detail/812985', self::BASE . '/actress/detail/812986'], array_column($movie['performers'], 'url'));
        self::assertSame(['スカトロ', '巨尻', '脱糞', 'レズ', 'ベスト・総集編'], $movie['tags']);
        self::assertSame([['url' => self::COVER, 'thumbnail_url' => null, 'metadata' => []]], $movie['screenshots']);
        self::assertSame('OPERA', $movie['metadata']['maker']);
        self::assertSame([['name' => 'スカトロ', 'url' => self::BASE . '/works/list/genre/116', 'external_id' => '116'], ['name' => '巨尻', 'url' => self::BASE . '/works/list/genre/484', 'external_id' => '484'], ['name' => '脱糞', 'url' => self::BASE . '/works/list/genre/416', 'external_id' => '416'], ['name' => 'レズ', 'url' => self::BASE . '/works/list/genre/111', 'external_id' => '111'], ['name' => 'ベスト・総集編', 'url' => self::BASE . '/works/list/genre/5009', 'external_id' => '5009']], $movie['metadata']['genres']);
        self::assertSame([['name' => 'OPERA総集編', 'url' => self::BASE . '/works/list/series/2795', 'external_id' => '2795']], $movie['metadata']['series']);
        self::assertArrayNotHasKey('label', $movie['metadata']);
        self::assertSame([['name' => '2023年8月22日', 'url' => self::BASE . '/works/list/date/2023-08-22', 'external_id' => '2023-08-22']], $movie['metadata']['release_links']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame([['format' => 'DVD', 'code' => 'OPBD209', 'duration' => 240, 'price' => '6,600円（税込）']], $movie['metadata']['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/litevideo/freepv/o/opb/opbd00209/opbd00209_dm_w.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/19/content/69865/sample_video_image_file/unFOB36GNHOBnOWmgiCM0lPymTz1lad5M5qTFcKr.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame([], $movie['metadata']['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $movie['metadata']['release_note']);
        self::assertSame('女優 白沢比呂 白石のん 白石らん', $movie['metadata']['source_fields']['女優']);
        self::assertSame('発売日 2023年8月22日', $movie['metadata']['source_fields']['発売日']);
        self::assertSame('シリーズ OPERA総集編', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('ジャンル スカトロ 巨尻 脱糞 レズ ベスト・総集編', $movie['metadata']['source_fields']['ジャンル']);
        self::assertSame('監督 ---', $movie['metadata']['source_fields']['監督']);
        self::assertSame('品番 DVDOPBD209', $movie['metadata']['source_fields']['品番']);
        self::assertSame('収録時間 DVD240分', $movie['metadata']['source_fields']['収録時間']);
        self::assertSame('価格 DVD6,600円（税込）', $movie['metadata']['source_fields']['価格']);
        self::assertCount(8, $movie['metadata']['source_fields']);
    }

    public function test_direct_listing_retains_card_fields(): void
    {
        $result = (new Listing($this->clientWithFixture('opera/list-2023-08-22.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2023-08-22'));
        self::assertCount(1, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('OPBD209', $movie['external_id']);
        self::assertSame(self::TITLE, $movie['title']);
        self::assertSame('OPBD209', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/19/content/69865/cuuetqxt6DTKHorUY4VGwDexb3bW201RhsJDLGZu.jpg', $movie['cover_url']);
        self::assertNull($movie['description']);
        self::assertSame('2023-08-22', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame([], $movie['performers']);
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame('OPERA', $movie['metadata']['maker']);
        self::assertSame(3, $movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2023年8月22日', $movie['metadata']['search_key']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_date_index_declares_follow_up_and_paginates_release_groups(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('opera/dates.html')],
            self::BASE . '/works/list/date/2023-08-22' => ['body' => $this->loadFixture('opera/list-2023-08-22.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(1, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('OPBD209', $movie['code']);
        $group = $movie['metadata']['release_group'];
        self::assertSame('2023-08-22', $group['date']);
        self::assertSame('2023-08-22', $group['external_id']);
        self::assertSame(self::BASE . '/works/list/date/2023-08-22', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(29, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_page_without_profile_data_is_rejected(): void
    {
        // The live OPERA actress page only carries the name and a works list (no profile rows), so the
        // manifest does not declare performer_detail and the shared guard rejects it.
        $this->expectException(CrawlParseException::class);
        (new PerformerDetail($this->clientWithFixture('opera/actress-812985.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/812985'));
    }
}
