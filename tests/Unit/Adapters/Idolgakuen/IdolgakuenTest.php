<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Idolgakuen;

use JOOservices\CrawlerX\Adapters\Idolgakuen\IdolgakuenCrawler;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Site;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Types\Detail;
use JOOservices\CrawlerX\Adapters\Idolgakuen\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class IdolgakuenTest extends TestCase
{
    private const BASE = 'https://idol-gakuen.jp';

    public function test_listing_extracts_card_fields_and_next_page_from_real_fixture(): void
    {
        $result = (new Listing($this->clientWithFixture('idolgakuen/listing-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/item-list/'));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(25, $result->items);
        self::assertSame(55, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/item-list/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);

        $first = $result->items[0];
        self::assertSame(self::BASE . '/item/116618/', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('116618', $movie['external_id']);
        self::assertSame('美尻の国【SOKMIL ポイント20倍】', $movie['title']);
        self::assertSame(
            self::BASE . '/wp/wp-content/uploads/2026/09/momijik-bijirinokuni-400-105x150.jpg',
            $movie['cover_url'],
        );
        self::assertSame('2026-10-23', $movie['date']);
        self::assertSame(['栗原もみじ'], array_column($movie['performers'], 'name'));
        self::assertStringStartsWith('令和最高のプロポーション', $movie['description']);
        self::assertSame('Idol Gakuen', $movie['metadata']['maker']);
    }

    public function test_page_two_reads_its_current_page_and_next_link_from_real_fixture(): void
    {
        $result = (new Listing($this->clientWithFixture('idolgakuen/listing-page2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/item-list/page/2/', page: 9));

        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(55, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/item-list/page/3/', $result->pagination->nextUrl);
        self::assertSame('113629', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_detail_extracts_each_labelled_field_and_image_pair_in_page_order(): void
    {
        $result = (new Detail($this->clientWithFixture('idolgakuen/detail-117040.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/item/117040/'));
        $movie = $result->meta['movie'];

        self::assertSame('movie', $result->entityType);
        self::assertSame('117040', $movie['external_id']);
        self::assertSame('TSDS-43134', $movie['code']);
        self::assertSame('長野雅「Eternal Love」', $movie['title']);
        self::assertSame(
            self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-400.jpg',
            $movie['cover_url'],
        );
        self::assertSame('透明感が半端ない美少女ながら、柔らかボディを水着に包んで披露してくれる。', $movie['description']);
        self::assertSame('2026-11-20', $movie['date']);
        self::assertSame(136, $movie['duration']);
        self::assertSame(['長野雅'], array_column($movie['performers'], 'name'));
        self::assertSame('長野雅', $movie['performers'][0]['name_japanese']);
        self::assertSame('ながの・みやび', $movie['performers'][0]['metadata']['name_kana']);
        self::assertSame('1999年10月3日', $movie['performers'][0]['birth_date_raw']);
        self::assertSame('T164cm', $movie['performers'][0]['size_raw']);
        self::assertSame([
            '生年月日' => '1999年10月3日',
            'サイズ' => 'T164cm',
            '出身地' => '愛知県',
            '血液型' => 'O型',
            '趣味' => '猫と遊ぶこと、ゲーム、映画鑑賞、アイドル鑑賞',
            '特技' => '水泳、どこでも寝られること',
            'X' => '@naganomiyabi',
        ], $movie['performers'][0]['raw_profile']);
        self::assertSame('愛知県', $movie['performers'][0]['metadata']['birthplace']);
        self::assertSame('O型', $movie['performers'][0]['metadata']['blood_type']);
        self::assertSame('猫と遊ぶこと、ゲーム、映画鑑賞、アイドル鑑賞', $movie['performers'][0]['metadata']['hobby']);
        self::assertSame('水泳、どこでも寝られること', $movie['performers'][0]['metadata']['special_skills']);
        self::assertSame('https://x.com/naganomiyabi', $movie['performers'][0]['metadata']['x_url']);
        self::assertCount(3, $movie['screenshots']);
        self::assertSame([
            [
                'url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h1-633x900.jpg',
                'thumbnail_url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h1-89x126.jpg',
                'metadata' => [],
            ],
            [
                'url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h4-631x900.jpg',
                'thumbnail_url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h4-88x126.jpg',
                'metadata' => [],
            ],
            [
                'url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h1h4-900x604.jpg',
                'thumbnail_url' => self::BASE . '/wp/wp-content/uploads/2026/08/miyabin-eternallove-h1h4-126x85.jpg',
                'metadata' => [],
            ],
        ], $movie['screenshots']);
        self::assertSame('Idol Gakuen', $movie['metadata']['maker']);
        self::assertSame([
            'amount' => 4730.0,
            'currency' => 'JPY',
            'label' => '4,730円（本体4,300円 税率10％）',
        ], $movie['metadata']['price']);
        self::assertSame('本編136分＋特典映像4分／カラー／日本語（ステレオ）／16：9 ビスタサイズ／NTSC／片面・一層', $movie['metadata']['specification']);
        self::assertSame([
            '出演者' => '長野雅',
            '発売日' => '2026年11月20日',
            '価格' => '4,730円（本体4,300円 税率10％）',
            '品番' => 'TSDS-43134',
            '仕様' => '本編136分＋特典映像4分／カラー／日本語（ステレオ）／16：9 ビスタサイズ／NTSC／片面・一層',
            '生年月日' => '1999年10月3日',
            'サイズ' => 'T164cm',
            '出身地' => '愛知県',
            '血液型' => 'O型',
            '趣味' => '猫と遊ぶこと、ゲーム、映画鑑賞、アイドル鑑賞',
            '特技' => '水泳、どこでも寝られること',
            'X' => '@naganomiyabi',
        ], $movie['metadata']['source_fields']);
        self::assertSame('ながの・みやび', $movie['metadata']['performer_name_kana']);
        self::assertSame('https://x.com/naganomiyabi', $movie['metadata']['performer_x_url']);
    }

    public function test_url_detection_matches_only_captured_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('idolgakuen');
        self::assertInstanceOf(IdolgakuenCrawler::class, $crawler);

        foreach ([
            '/item-list/' => CrawlType::Listing,
            '/item-list/page/2/' => CrawlType::Listing,
            '/item/117040/' => CrawlType::Detail,
        ] as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }

        self::assertFalse($crawler->detectUrl(self::BASE . '/item-video-list/')->matched);
        self::assertSame('117040', Site::externalId(self::BASE . '/item/117040/'));
        self::assertNull(Site::externalId(self::BASE . '/item-list/'));
    }
}
