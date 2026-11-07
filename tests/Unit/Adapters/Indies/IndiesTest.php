<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Indies;

use JOOservices\CrawlerX\Adapters\Indies\IndiesCrawler;
use JOOservices\CrawlerX\Adapters\Indies\Types\Detail;
use JOOservices\CrawlerX\Adapters\Indies\Types\Listing;
use JOOservices\CrawlerX\Adapters\Indies\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Indies\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class IndiesTest extends TestCase
{
    private const BASE = 'https://www.indies-av.co.jp';

    private const UPLOADS = self::BASE . '/wp-content/uploads/';

    public function test_detail_page_yields_every_field_it_offers(): void
    {
        $url = self::BASE . '/title/dsdp049/';
        $result = (new Detail($this->clientWithFixture('indies/detail-dsdp049.html')))->execute(new CrawlRequestDto(url: $url));
        $movie = $result->meta['movie'];

        self::assertSame($url, $result->url);
        self::assertSame('dsdp049', $movie['external_id']);
        self::assertSame('プリケツアナル グレイテストヒップ!4', $movie['title']);
        self::assertSame('DSDP-049', $movie['code']);
        self::assertStringStartsWith('尻好き選りすぐり美女10人8シーンのアナルを過激に見せます。', $movie['description']);
        self::assertStringEndsWith('様々な尻穴挿入をお楽しみください。', $movie['description']);
        self::assertSame(self::UPLOADS . 'Package/2000/DSDP-049_2000.jpg', $movie['cover_url']);
        self::assertSame('2026-11-03', $movie['date']);
        self::assertSame(240, $movie['duration']);
        self::assertSame(
            ['アリーナ・ロペス', 'ミスティ・ミーナー', 'ハーレイ・ジェイド', 'シャーロット・サーチュラ', 'アンナ・デ・ヴィル', 'シーナ・ショウ', 'セイレン・デ・マー', 'アリス・マーチ', 'アニッサ・ケイト', 'エマ・ヒックス'],
            array_column($movie['performers'], 'name'),
        );
        self::assertSame(['アナル', '尻フェチ', '洋ピン'], $movie['tags']);

        self::assertCount(20, $movie['screenshots']);
        self::assertSame(self::UPLOADS . 'Package/DSDP-049/DSDP-049-1.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::UPLOADS . 'Package/DSDP-049/DSDP-049-1.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(self::UPLOADS . 'Package/DSDP-049/DSDP-049-20.jpg', $movie['screenshots'][19]['url']);

        $meta = $movie['metadata'];
        self::assertSame('桃太郎映像出版', $meta['maker']);
        self::assertSame('SWEET DEVIL Pink', $meta['series']);
        self::assertSame('SWEET DEVIL Pink', $meta['label']);
        self::assertSame(self::BASE . '/lables/dsdp/', $meta['label_url']);
        self::assertSame('Marchin', $meta['director']);
        self::assertSame('監修', $meta['director_role']);
        self::assertSame(['amount' => 3180.0, 'currency' => 'JPY', 'label' => '¥3,180 （税込 ¥3,498）'], $meta['price']);
        self::assertSame(['amount' => 3498.0, 'currency' => 'JPY', 'label' => null], $meta['price_with_tax']);
        self::assertSame('4573653444553', $meta['jan']);
        self::assertSame('317403', $meta['review_number']);
        self::assertSame('2026年11月03日', $meta['release_date_raw']);
        self::assertSame(
            [
                ['name' => 'アナル', 'url' => self::BASE . '/genre/anal/'],
                ['name' => '尻フェチ', 'url' => self::BASE . '/genre/%e5%b0%bb%e3%83%95%e3%82%a7%e3%83%81/'],
                ['name' => '洋ピン', 'url' => self::BASE . '/genre/%e6%b4%8b%e3%83%94%e3%83%b3/'],
            ],
            $meta['genres'],
        );
        self::assertSame(self::UPLOADS . 'Package/2000/DSDP-049_960.jpg', $meta['cover_thumbnail_url']);
        self::assertSame(self::UPLOADS . 'MOV/DSDP-049.mp4', $meta['sample_video_url']);
        self::assertSame(self::UPLOADS . 'Package/2000/DSDP-049_2000.jpg', $meta['sample_video_poster_url']);
        self::assertSame(
            [
                ['name' => 'DMMで商品を購入', 'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=dsdp049/momotaro-990'],
                ['name' => 'DMMで配信視聴', 'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=dsdp00049/momotaro-990'],
                ['name' => 'DUGAで商品を視聴', 'url' => 'https://click.duga.jp/ppv/momotaro/23618-10'],
                ['name' => 'FANZA', 'url' => 'https://video.dmm.co.jp/av/list/?key=%E6%A1%83%E5%A4%AA%E9%83%8E%E6%98%A0%E5%83%8F%20'],
            ],
            $meta['purchase_links'],
        );
        self::assertSame(
            [
                '女優名' => 'アリーナ・ロペス/ミスティ・ミーナー/ハーレイ・ジェイド/シャーロット・サーチュラ/アンナ・デ・ヴィル/シーナ・ショウ/セイレン・デ・マー/アリス・マーチ/アニッサ・ケイト/エマ・ヒックス',
                '品番' => 'DSDP-049',
                'シリーズ' => 'SWEET DEVIL Pink',
                '監督' => '監修：Marchin',
                '定価' => '¥3,180 （税込 ¥3,498）',
                '収録時間' => '240分',
                '発売日' => '2026年11月03日',
                'キーワード' => 'アナル、尻フェチ、洋ピン',
                'JAN' => '4573653444553',
                '審査番号' => '317403',
            ],
            $meta['source_fields'],
        );
        self::assertStringNotContainsString('DSD-803', (string) json_encode($movie), 'the recommended block is not data');
    }

    public function test_month_listing_cards_and_next_month_link(): void
    {
        $url = self::BASE . '/ym/202611/';
        $result = (new Listing($this->clientWithFixture('indies/list-202611.html')))->execute(new CrawlRequestDto(url: $url));

        self::assertSame('movie', $result->entityType);
        self::assertCount(11, $result->items);
        self::assertSame(self::BASE . '/title/hntd004/', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('hntd004', $movie['external_id']);
        self::assertSame('素人美女ナンパ HUNTING×HUNTING Neo　日本橋で見つけた品格の奥に宿る艶やかアラサー美女たち', $movie['title']);
        self::assertSame('HNTD-004', $movie['code']);
        self::assertStringStartsWith('日本の中心地「日本橋」。', $movie['description']);
        self::assertStringEndsWith('大噴射ザーメンを口で受けとめ顔射状態！', $movie['description']);
        self::assertSame('2026-11-03', $movie['date']);
        self::assertSame(self::UPLOADS . 'Package/HNTD-004/HNTD-004_250.jpg', $movie['cover_url']);
        self::assertSame([], $movie['tags']);
        self::assertSame('桃太郎映像出版', $movie['metadata']['maker']);
        self::assertSame(['amount' => 3180.0, 'currency' => 'JPY', 'label' => null], $movie['metadata']['price']);
        self::assertSame('4573653444461', $movie['metadata']['jan']);
        self::assertSame('作品情報', $movie['metadata']['listing_kind']);
        self::assertSame('2026年11月', $movie['metadata']['listing_heading']);
        self::assertSame('DSDP-050', $result->items[10]->meta['movie']['code']);

        self::assertSame(1, $result->pagination->currentPage);
        self::assertNull($result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/ym/202612/', $result->pagination->nextUrl);
    }

    public function test_label_listing_paginates_with_page_links(): void
    {
        $url = self::BASE . '/lables/dsdp/';
        $result = (new Listing($this->clientWithFixture('indies/list-lables-dsdp.html')))->execute(new CrawlRequestDto(url: $url));

        self::assertCount(40, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('dsdp052', $movie['external_id']);
        self::assertSame('爆乳ナースのエロ過ぎる病棟巡回', $movie['title']);
        self::assertSame('DSDP-052', $movie['code']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame('4573653445109', $movie['metadata']['jan']);
        self::assertSame(self::UPLOADS . 'Package/DSDP-052/DSDP-052_250.jpg', $movie['cover_url']);
        self::assertSame('シリーズ一覧', $movie['metadata']['listing_kind']);
        self::assertSame('SWEET DEVIL Pink', $movie['metadata']['listing_heading']);
        self::assertSame('DSDP-013', $result->items[39]->meta['movie']['code']);
        self::assertSame(2, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/lables/dsdp/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_genre_listing_cards_carry_their_genre_badges(): void
    {
        $url = self::BASE . '/genre/anal/';
        $result = (new Listing($this->clientWithFixture('indies/list-genre-anal.html')))->execute(new CrawlRequestDto(url: $url));

        self::assertCount(40, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('dsdp049', $movie['external_id']);
        self::assertSame(['アナル', '尻フェチ', '洋ピン'], $movie['tags']);
        self::assertSame('作品のジャンル', $movie['metadata']['listing_kind']);
        self::assertSame('アナル', $movie['metadata']['listing_heading']);
        self::assertSame(['アナル', 'アナル二本挿し', 'オイル', '尻フェチ', '洋ピン'], $result->items[1]->meta['movie']['tags']);
        self::assertSame(7, $result->pagination->lastPage);
        self::assertSame(self::BASE . '/genre/anal/page/2/', $result->pagination->nextUrl);
    }

    public function test_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><section class="inner"><h1>x</h1></section></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/ym/209901/'));
    }

    public function test_actress_profile_with_every_row_filled(): void
    {
        $url = self::BASE . '/actress/kana_yura/';
        $result = (new PerformerDetail($this->clientWithFixture('indies/actress-kana_yura.html')))->execute(new CrawlRequestDto(url: $url));
        $person = $result->meta['performer'];

        self::assertSame('kana_yura', $person['external_id']);
        self::assertSame($url, $person['url']);
        self::assertSame('由良かな', $person['name']);
        self::assertSame(self::UPLOADS . '2024/03/kana_yura_top.jpg', $person['profile_image_url']);
        self::assertSame('2003-11-29', $person['birth_date_raw']);
        self::assertSame('143cm', $person['height_raw']);
        self::assertSame('B:85cm(Bカップ) / W: 58cm / H:88cm', $person['size_raw']);
        self::assertSame(
            [
                '女優名' => '由良かな',
                'ヨミガナ' => 'ユラカナ',
                '生年月日' => '2003年11月29日',
                '身長' => '143cm',
                'スリーサイズ' => 'B:85cm(Bカップ) / W: 58cm / H:88cm',
                '趣味 / 特技' => '歌／ギター',
            ],
            $person['raw_profile'],
        );
        self::assertSame('ユラカナ', $person['metadata']['reading']);
        self::assertSame([['type' => 'twitter', 'url' => 'https://twitter.com/yura_kana_']], $person['metadata']['social_links']);
        self::assertCount(10, $person['metadata']['works']);
    }

    public function test_actress_profile_drops_empty_template_rows_and_lists_works(): void
    {
        $url = self::BASE . '/actress/mitsuki_shirayuki/';
        $result = (new PerformerDetail($this->clientWithFixture('indies/actress-mitsuki_shirayuki.html')))->execute(new CrawlRequestDto(url: $url));
        $person = $result->meta['performer'];

        self::assertSame('mitsuki_shirayuki', $person['external_id']);
        self::assertSame('白雪美月', $person['name']);
        self::assertSame(self::UPLOADS . '2025/11/mitsuki_shirayuki_top.jpg', $person['profile_image_url']);
        self::assertSame('2000-11-29', $person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertNull($person['size_raw']);
        self::assertSame(['女優名' => '白雪美月', 'ヨミガナ' => 'シラユキミツキ', '生年月日' => '2000年11月29日'], $person['raw_profile']);
        self::assertSame('シラユキミツキ', $person['metadata']['reading']);
        self::assertSame([['type' => 'twitter', 'url' => 'https://twitter.com/Shira_Hana_Mitu']], $person['metadata']['social_links']);
        self::assertCount(10, $person['metadata']['works']);
        self::assertSame(
            [
                'external_id' => 'mmpb147',
                'code' => 'MMPB-147',
                'title' => '自分の娘くらい年下のきゃわいい女の子の性処理玩具になりたいっ！',
                'url' => self::BASE . '/title/mmpb147/',
                'cover_url' => self::UPLOADS . 'Package/MMPB-147/MMPB-147_250.jpg',
            ],
            $person['metadata']['works'][0],
        );
        self::assertSame('ymdd471', $person['metadata']['works'][9]['external_id']);
    }

    public function test_actress_listing_cards(): void
    {
        $url = self::BASE . '/actpage/';
        $result = (new PerformerListing($this->clientWithFixture('indies/actpage.html')))->execute(new CrawlRequestDto(url: $url));

        self::assertSame('performer', $result->entityType);
        self::assertCount(9, $result->items);
        $person = $result->items[0]->meta['performer'];
        self::assertSame('mitsuki_shirayuki', $person['external_id']);
        self::assertSame('白雪美月', $person['name']);
        self::assertSame(self::BASE . '/actress/mitsuki_shirayuki/', $person['url']);
        self::assertSame(self::UPLOADS . '2025/11/mitsuki_shirayuki_top.jpg', $person['profile_image_url']);
        self::assertSame('kana_yura', $result->items[1]->meta['performer']['external_id']);
        self::assertSame('由良かな', $result->items[1]->meta['performer']['name']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
    }

    public function test_actress_listing_without_cards_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new PerformerListing($this->clientWithHtml('<html><body><section class="inner"></section></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/actpage/'));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('indies');
        self::assertInstanceOf(IndiesCrawler::class, $crawler);
        $expected = [
            '/ym/202611/' => CrawlType::Listing,
            '/lables/dsdp/' => CrawlType::Listing,
            '/lables/dsdp/page/2/' => CrawlType::Listing,
            '/genre/anal/page/3/' => CrawlType::Listing,
            '/genre/%e5%b0%bb%e3%83%95%e3%82%a7%e3%83%81/' => CrawlType::Listing,
            '/title/dsdp049/' => CrawlType::Detail,
            '/actress/mitsuki_shirayuki/' => CrawlType::PerformerDetail,
            '/actpage/' => CrawlType::PerformerListing,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/specials/')->matched);
        self::assertSame('indies', $crawler->name());
    }
}
