<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

final class ExtendedAdapterCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_avfan_listing_and_detail_through_facade(): void
    {
        FixtureResponder::for('GET', 'https://avfan.com/en')->file('Avfan/listing-placeholder.html');
        $list = CrawlerX::url('https://avfan.com/en')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $list);
        self::assertNotEmpty($list->items);

        FixtureResponder::for('GET', 'https://avfan.com/en/movies/ewTlM3Fj')->file('Avfan/detail-ewTlM3Fj.html');
        $item = CrawlerX::url('https://avfan.com/en/movies/ewTlM3Fj')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('ewTlM3Fj', $item->meta['movie']['external_id']);
    }

    public function test_xcity_detail_and_performer_routes_through_facade(): void
    {
        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/detail/5517/', 'xcity/detail-performer-5517.html');
        $performer = CrawlerX::url('https://xxx.xcity.jp/idol/detail/5517/')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $performer);
        self::assertSame('Ai Uehara', $performer->meta['performer']['name']);

        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/?ini=a', 'xcity/listing-idol-index.html');
        $index = CrawlerX::url('https://xxx.xcity.jp/idol/?ini=a')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $index);

        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/?kana=a', 'xcity/listing-idol-kana-a.html');
        $kana = CrawlerX::url('https://xxx.xcity.jp/idol/?kana=a')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $kana);

        $this->respondWithFixture('GET', 'https://xxx.xcity.jp/idol/?ini=a&page=1', 'xcity/listing-performer-ini-a.html');
        $performers = CrawlerX::url('https://xxx.xcity.jp/idol/?ini=a&page=1')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $performers);
        self::assertNotEmpty($performers->items);
    }

    public function test_javbtc_and_javbus_through_facade(): void
    {
        $this->respondWithFixture('GET', 'https://javbtc.com/', 'javbtc/listing-page-1.html');
        $list = CrawlerX::url('https://javbtc.com/')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $list);

        $this->respondWithFixture('GET', 'https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity', 'javbtc/detail-yua-mikami.html');
        $item = CrawlerX::url('https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $item);

        $this->respondWithFixture('GET', 'https://www.javbus.com/en/', 'javbus/listing-live.html');
        $javbus = CrawlerX::url('https://www.javbus.com/en/')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $javbus);
        self::assertGreaterThan(20, count($javbus->items));

        $this->respondWithFixture('GET', 'https://www.javbus.com/en/NAMH-074', 'javbus/detail-namh-074-live.html');
        $javbusDetail = CrawlerX::url('https://www.javbus.com/en/NAMH-074')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $javbusDetail);
        self::assertSame('NAMH-074', $javbusDetail->meta['movie']['external_id']);

        $this->respondWithFixture('GET', 'https://www.javbus.com/en/actresses', 'javbus/performer-listing-live.html');
        $actresses = CrawlerX::url('https://www.javbus.com/en/actresses')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $actresses);
        self::assertGreaterThan(20, count($actresses->items));

        $this->respondWithFixture('GET', 'https://www.javbus.com/en/star/11p7', 'javbus/performer-detail-11p7-live.html');
        $actress = CrawlerX::url('https://www.javbus.com/en/star/11p7')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $actress);
        self::assertSame('11p7', $actress->meta['performer']['external_id']);
    }

    public function test_minnanoav_and_missav_through_facade(): void
    {
        $this->respondWithFixture('GET', 'https://www.minnano-av.com/actress_list.php', 'minnanoav/listing_page1.html');
        $performers = CrawlerX::url('https://www.minnano-av.com/actress_list.php')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $performers);

        $this->respondWithFixture('GET', 'https://www.minnano-av.com/actress945093.html', 'minnanoav/detail_rich.html');
        $performer = CrawlerX::url('https://www.minnano-av.com/actress945093.html')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $performer);

        $this->respondWithFixture('GET', 'https://missav.ws/en/new', 'missav/listing-new.html');
        $listing = CrawlerX::url('https://missav.ws/en/new')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $listing);
        self::assertNotEmpty($listing->items);

        $this->respondWithFixture('GET', 'https://missav.ws/en/fns-247', 'missav/detail-fns-247.html');
        $detail = CrawlerX::url('https://missav.ws/en/fns-247')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $detail);
    }

    public function test_minnanoav_movie_routes_and_xcity_index_through_facade(): void
    {
        $filmographyUrl = 'https://www.minnano-av.com/actress.php?actress_id=945093';
        $this->respondWithFixture('GET', $filmographyUrl, 'minnanoav/filmography_page1.html');
        $filmography = CrawlerX::url($filmographyUrl)->site('minnanoav')->type(CrawlType::Listing)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $filmography);
        self::assertCount(49, $filmography->items);

        $movieUrl = 'https://www.minnano-av.com/av570850.html';
        $this->respondWithFixture('GET', $movieUrl, 'minnanoav/av570850.html');
        $movie = CrawlerX::url($movieUrl)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $movie);
        self::assertSame('av570850', $movie->meta['movie']['external_id']);

        $indexUrl = 'https://xxx.xcity.jp/idol/';
        $this->respondWithFixture('GET', $indexUrl, 'xcity/listing-idol-index.html');
        $index = CrawlerX::url($indexUrl)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $index);
        self::assertGreaterThanOrEqual(10, count($index->items));
    }

    public function test_onepondo_detail_through_facade(): void
    {
        $detail = $this->loadFixture('onepondo/detail-060426_001.json');
        $gallery = $this->loadFixture('onepondo/gallery-060426_001.json');
        $movieUrl = 'https://en.1pondo.tv/movies/060426_001/';

        FixtureResponder::for('GET', 'https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json')->body($detail);
        FixtureResponder::for('GET', 'https://en.1pondo.tv/dyn/dla/json/movie_gallery/060426_001.json')->body($gallery);

        FixtureResponder::for('GET', $movieUrl)->body($detail);
        $item = CrawlerX::url($movieUrl)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('060426_001', $item->meta['movie']['external_id']);
    }

    public function test_onejav_tag_listing_and_ffjav_detail_through_facade(): void
    {
        $tagUrl = 'https://onejav.com/tag/FC2';
        $this->respondWithFixture('GET', $tagUrl, 'onejav/tag-fc2/page-1.html');
        $tagList = CrawlerX::url($tagUrl)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $tagList);
        self::assertNotEmpty($tagList->items);
        self::assertSame(2, $tagList->pagination->nextPage);
        self::assertSame('https://onejav.com/tag/FC2?page=2', $tagList->pagination->nextUrl);

        $this->respondWithFixture('GET', 'https://ffjav.com/torrent/mida-768', 'ffjav/detail-sample-1.html');
        $detail = CrawlerX::url('https://ffjav.com/torrent/mida-768')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $detail);
    }

    public function test_javlibrary_current_routes_through_facade(): void
    {
        $this->respondWithFixture('GET', 'https://www.javlibrary.com/en/vl_newrelease.php', 'JavLibrary/listing-live.html');
        $listing = CrawlerX::url('https://www.javlibrary.com/en/vl_newrelease.php')->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $listing);
        self::assertCount(20, $listing->items);

        $this->respondWithFixture('GET', 'https://www.javlibrary.com/en/javme3rasy.html', 'JavLibrary/detail-javme3rasy-live.html');
        $detail = CrawlerX::url('https://www.javlibrary.com/en/javme3rasy.html')->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $detail);
        self::assertSame('javme3rasy', $detail->meta['movie']['external_id']);

        $performerListUrl = 'https://www.javlibrary.com/en/star_list.php?prefix=A';
        $this->respondWithFixture('GET', $performerListUrl, 'JavLibrary/performer-listing-live.html');
        $performers = CrawlerX::url($performerListUrl)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $performers);
        self::assertGreaterThan(50, count($performers->items));

        $performerUrl = 'https://www.javlibrary.com/en/vl_star.php?s=ayubu';
        $this->respondWithFixture('GET', $performerUrl, 'JavLibrary/performer-detail-ayubu-live.html');
        $performer = CrawlerX::url($performerUrl)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $performer);
        self::assertSame('ayubu', $performer->meta['performer']['external_id']);
    }

    public function test_jable_detail_fixtures_through_facade(): void
    {
        $cases = [
            ['https://en.jable.tv/videos/dsod-031/', 'jable/detail-dsod-031.html'],
            ['https://en.jable.tv/videos/royd-348/', 'jable/detail-royd-348.html'],
            ['https://en.jable.tv/videos/abf-382/', 'jable/detail-abf-382.html'],
            ['https://en.jable.tv/videos/sora-599/', 'jable/detail-sora-599.html'],
            ['https://en.jable.tv/videos/jur-411/', 'jable/detail-jur-411.html'],
            ['https://en.jable.tv/videos/mida-673/', 'jable/detail-mida-673.html'],
            ['https://en.jable.tv/videos/royd-309/', 'jable/detail-royd-309.html'],
            ['https://en.jable.tv/videos/start-596/', 'jable/detail-start-596.html'],
            ['https://en.jable.tv/videos/pred-877/', 'jable/detail-pred-877.html'],
            ['https://en.jable.tv/videos/fjin-091/', 'jable/detail-fjin-091.html'],
            ['https://en.jable.tv/videos/hnd-906/', 'jable/detail-hnd-906.html'],
            ['https://en.jable.tv/videos/pred-767/', 'jable/detail-pred-767.html'],
        ];

        foreach ($cases as [$url, $fixture]) {
            $this->respondWithFixture('GET', $url, $fixture);
            $item = CrawlerX::url($url)->crawl();
            self::assertInstanceOf(CrawlItemResultDto::class, $item);
            self::assertNotSame('', trim((string) $item->meta['movie']['title']));
        }
    }

    public function test_alternate_adapter_pages_through_facade(): void
    {
        foreach ([
            ['https://en.jable.tv/new-release/2/', 'jable/listing-new-release-page2.html', 2, true],
            ['https://en.jable.tv/new-release/1625/', 'jable/listing-new-release-last-page.html', 1625, false],
        ] as [$url, $fixture, $page, $hasNext]) {
            $this->respondWithFixture('GET', $url, $fixture);
            $listing = CrawlerX::url($url)->page($page)->crawl();
            self::assertInstanceOf(CrawlListResultDto::class, $listing);
            self::assertSame($hasNext, $listing->pagination->hasNextPage);
        }

        $javbtcUrl = 'https://javbtc.com/r18/yua-mikami';
        $this->respondWithFixture('GET', $javbtcUrl, 'javbtc/listing-performer.html');
        $javbtc = CrawlerX::url($javbtcUrl)->site('javbtc')->type(CrawlType::Listing)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $javbtc);
        self::assertGreaterThan(10, count($javbtc->items));

        $minnanoUrl = 'https://www.minnano-av.com/actress60273.html';
        $this->respondWithFixture('GET', $minnanoUrl, 'minnanoav/detail_sparse.html');
        $minnano = CrawlerX::url($minnanoUrl)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $minnano);
        self::assertSame('60273', $minnano->meta['performer']['external_id']);

        $warashiUrl = 'https://warashi-asian-pornstars.fr/en/s-2-0/maya-mutsuki/asian-female-pornstar/5678';
        $this->respondWithFixture('GET', $warashiUrl, 'warashi/detail_partial_fields.html');
        $warashi = CrawlerX::url($warashiUrl)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $warashi);
        self::assertSame('5678', $warashi->meta['performer']['external_id']);

        $onePondoUrl = 'https://en.1pondo.tv/list/?o=n&page=2';
        FixtureResponder::for('GET', $onePondoUrl)->body('{}');
        $this->respondWithFixture(
            'GET',
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json',
            'onepondo/list-newest-0.json',
        );
        $this->respondWithFixture(
            'GET',
            'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_50.json',
            'onepondo/list-newest-50.json',
        );
        $onePondo = CrawlerX::url($onePondoUrl)->site('onepondo')->type(CrawlType::Listing)->page(2)->crawl();
        self::assertInstanceOf(CrawlListResultDto::class, $onePondo);
        self::assertSame(2, $onePondo->page);
    }

    public function test_javdatabase_partial_live_capture_through_facade(): void
    {
        $url = 'https://www.javdatabase.com/idols/maya-mutsuki/';
        $this->respondWithFixture('GET', $url, 'javdatabase/detail_partial_fields.html');
        $item = CrawlerX::url($url)->crawl();
        self::assertInstanceOf(CrawlItemResultDto::class, $item);
        self::assertSame('maya-mutsuki', $item->meta['performer']['external_id']);
        self::assertSame('Maya Mutsuki', $item->meta['performer']['name']);
    }
}
