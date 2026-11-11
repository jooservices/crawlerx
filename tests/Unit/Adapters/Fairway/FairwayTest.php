<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Fairway;

use JOOservices\CrawlerX\Adapters\Fairway\FairwayCrawler;
use JOOservices\CrawlerX\Adapters\Fairway\Site;
use JOOservices\CrawlerX\Adapters\Fairway\Types\Detail;
use JOOservices\CrawlerX\Adapters\Fairway\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class FairwayTest extends TestCase
{
    private const BASE = 'https://fairway-av.com';

    public function test_release_listing_extracts_every_card_field_and_reports_no_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('fairway/list-release.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/works/list/release'));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(4, $result->items);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);

        $expected = [
            [
                'FWAY107', '清純は才能', 'https://cdn.up-timely.com/image/33/content/88119/lzNCgdASoDDwjZaFZgkJ2oNl0FVYQTExGJtwvKtM.jpg',
                '2026-11-10', '蒼井すず', '2026年11月10日発売',
            ],
            [
                'FWAY108', 'ロストヴァージン・プロジェクト', 'https://cdn.up-timely.com/image/33/content/88120/O651cUpzq5TAtaBzEDaAC0TeL00UWz3JbkG9DNYl.jpg',
                '2026-11-10', '博多彩葉', '2026年11月10日発売',
            ],
            [
                'FWAY101', '匂い立つ色気と甘く芳醇な裸体', 'https://cdn.up-timely.com/image/33/content/87641/AnJXtvNAKsoybbE7vfdA0rQv5gzafTV8sREJSB7S.jpg',
                '2026-10-13', '蜜このは', '2026年10月13日発売',
            ],
            [
                'FWAY104', '好きだぜコノヤロー', 'https://cdn.up-timely.com/image/33/content/87642/SlNN0jq1m9GxWMW9GRfEIFWZP928NfEfsdJSNFGD.jpg',
                '2026-10-13', '瀬戸環奈', '2026年10月13日発売',
            ],
        ];

        foreach ($result->items as $index => $item) {
            [$code, $title, $cover, $date, $performer, $group] = $expected[$index];
            $movie = $item->meta['movie'];
            self::assertSame(self::BASE . '/works/detail/' . $code, $item->url);
            self::assertSame($code, $movie['external_id']);
            self::assertSame($code, $movie['code']);
            self::assertSame($title, $movie['title']);
            self::assertSame($cover, $movie['cover_url']);
            self::assertSame($date, $movie['date']);
            self::assertSame([$performer], array_column($movie['performers'], 'name'));
            self::assertSame('FAIR＆WAY', $movie['metadata']['maker']);
            self::assertSame($group, $movie['metadata']['release_group']);
        }
    }

    public function test_reservation_listing_extracts_its_card_and_date(): void
    {
        $result = (new Listing($this->clientWithFixture('fairway/list-reserve.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/works/list/reserve'));

        self::assertCount(1, $result->items);
        $item = $result->items[0];
        $movie = $item->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/FWAY105', $item->url);
        self::assertSame('FWAY105', $movie['external_id']);
        self::assertSame('FWAY105', $movie['code']);
        self::assertSame('制服フリマ', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/33/content/88630/wuFOZlmwfGSTLtZmilG026bImVszpORnjxQspgIZ.jpg', $movie['cover_url']);
        self::assertSame('2026-12-08', $movie['date']);
        self::assertSame(['井上もも'], array_column($movie['performers'], 'name'));
        self::assertSame('2026年12月8日発売', $movie['metadata']['release_group']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_detail_extracts_all_labelled_fields_formats_and_work_images(): void
    {
        $url = self::BASE . '/works/detail/FWAY088?page_from=genre&sys_code=214';
        $result = (new Detail($this->clientWithFixture('fairway/detail-FWAY088.html')))
            ->execute(new CrawlRequestDto(url: $url));
        $movie = $result->meta['movie'];

        self::assertSame($url, $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame('FWAY088', $movie['external_id']);
        self::assertSame('FWAY088', $movie['code']);
        self::assertSame('MONSTER', $movie['title']);
        self::assertSame('最強ヒロイン・瀬戸環奈の最新イメージDVDを緊急発売！ファン待望のコスプレ衣装に身を包んだ神プロポーションのセトカンが、時に可愛く時にセクシーに弾けて動く必見の映像集！透き通るような色白美肌のヌードイメージは本年度最高傑作！', $movie['description']);
        self::assertSame('2026-01-13', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(['瀬戸環奈'], array_column($movie['performers'], 'name'));
        self::assertSame(['巨乳', 'スレンダー', 'コスプレ', '長身', 'イメージビデオ'], $movie['tags']);

        $imageUrls = [
            'https://cdn.up-timely.com/image/33/content/83198/qYKiUpmvMhe4zW3EG6yacB6YXR8T0t65km52u84w.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/CW1PawBv0jiBIRAIb0aRbsBdfrs82P1Pw45kvG94.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/9EShO3VDfl0kPTBu0H60ANsW6zgRwBXhYkDuAknQ.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/EA97xLuePQADs98v5TPRD8Akan1EUWl3sqj1NgYo.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/mDILqmWQA2o8y2zuEOu9y3W99lfGEXPutaQjyStl.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/bY687fuQ7oZdl76wT7iSr2NS1dQwGrp8mmRzdIvI.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/egEWxWq6PWxliUm0zLhXG3BHCtuiYrJcNBiW0bAJ.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/CEslpLbWiIe1Yy8EONo2tzc5GdaioAh1i4aLP9Dm.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/jji6np0LfZyhVSmwnp7Z38pRUCyQgLIhIIesSbba.jpg',
            'https://cdn.up-timely.com/image/33/content/83198/zlKuGDl7pyEqVHFhcXW1BRs0Y8DwGI3ZcfGKRZe8.jpg',
        ];
        self::assertSame($imageUrls[0], $movie['cover_url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame($imageUrls, array_column($movie['screenshots'], 'url'));

        $metadata = $movie['metadata'];
        self::assertSame('FAIR＆WAY', $metadata['maker']);
        self::assertSame([
            ['name' => '巨乳', 'url' => self::BASE . '/works/list/genre/215', 'external_id' => '215'],
            ['name' => 'スレンダー', 'url' => self::BASE . '/works/list/genre/214', 'external_id' => '214'],
            ['name' => 'コスプレ', 'url' => self::BASE . '/works/list/genre/321', 'external_id' => '321'],
            ['name' => '長身', 'url' => self::BASE . '/works/list/genre/384', 'external_id' => '384'],
            ['name' => 'イメージビデオ', 'url' => self::BASE . '/works/list/genre/5016', 'external_id' => '5016'],
        ], $metadata['genres']);
        self::assertSame([
            [
                'format' => 'DVD',
                'code' => 'FWAY088',
                'duration' => 120,
                'price' => ['amount' => 4598.0, 'currency' => 'JPY', 'label' => '4,598円（税込）'],
            ],
            [
                'format' => 'Blu-ray',
                'code' => '9FWAY088',
                'duration' => 120,
                'price' => ['amount' => 5698.0, 'currency' => 'JPY', 'label' => '5,698円（税込）'],
            ],
        ], $metadata['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/litevideo/freepv/f/fwa/fway00088/fway00088_dm_w.mp4', $metadata['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/33/content/83198/sample_video_image_file/n3ii5z7fji8zjxrVAq3HgRfc4xd8LfgC6cuAF4iP.jpg', $metadata['sample_video_poster_url']);
        self::assertSame([
            '動画を購入' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=fway00088/WILLaffi-101',
            'DVDを購入' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=fway088/WILLaffi-101',
            'Blu-rayを購入' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=9fway088/WILLaffi-101',
        ], array_column($metadata['purchase_links'], 'url', 'label'));
        self::assertSame([
            '女優' => '女優 瀬戸環奈',
            '発売日' => '発売日 2026年1月13日',
            'シリーズ' => 'シリーズ',
            'ジャンル' => 'ジャンル 巨乳 スレンダー コスプレ 長身 イメージビデオ',
            '監督' => '監督',
            '品番' => '品番 DVDFWAY088 Blu-ray9FWAY088',
            '収録時間' => '収録時間 DVD120分 Blu-ray120分',
            '価格' => '価格 DVD4,598円（税込） Blu-ray5,698円（税込）',
        ], $metadata['source_fields']);
        self::assertArrayNotHasKey('series', $metadata);
        self::assertArrayNotHasKey('director', $metadata);
        self::assertStringNotContainsString('FWAY065', json_encode($movie, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('logo_image', json_encode($movie, JSON_UNESCAPED_UNICODE));
    }

    public function test_listing_without_work_cards_fails(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><p>empty</p></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/works/list/release'));
    }

    public function test_urls_are_limited_to_captured_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('fairway');
        self::assertInstanceOf(FairwayCrawler::class, $crawler);

        foreach ([
            '/works/list/release' => CrawlType::Listing,
            '/works/list/reserve' => CrawlType::Listing,
            '/works/detail/FWAY088?page_from=genre&sys_code=214' => CrawlType::Detail,
        ] as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }

        foreach (['/top', '/works/list/genre/214', '/works/list/release/page/2'] as $path) {
            self::assertFalse($crawler->detectUrl(self::BASE . $path)->matched, $path);
        }
        self::assertSame('fairway', $crawler->name());
        self::assertSame('FWAY088', Site::externalId(self::BASE . '/works/detail/FWAY088?page_from=genre'));
        self::assertNull(Site::externalId(self::BASE . '/works/list/release'));
    }
}
