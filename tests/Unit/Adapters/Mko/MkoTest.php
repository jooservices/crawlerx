<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mko;

use JOOservices\CrawlerX\Adapters\Mko\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class MkoTest extends TestCase
{
    private const BASE = 'https://mko-labo.net';

    public function test_detail_fields_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('mko/detail-MISM459.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/MISM459'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MISM459', $result->url);
        self::assertSame('MISM459', $movie['external_id']);
        self::assertSame('肛門W発狂 真：従順アナルメイドコレクション', $movie['title']);
        self::assertSame('MISM459', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88346/jmlWIhWomBWssti31t4DFNJ6ebbomEDB5ylTgDI3.jpg', $movie['cover_url']);
        self::assertSame('汗をぬぐうケツ穴召使いの肛門絶頂。ご主人様が好きすぎるが故に、アナルぽっかり切磋琢磨する尻穴真正依存メイド共。異質ロングディルドで連結し、S字結腸に刺激を与える発狂黒穴性交の応酬。腸奥の痙攣が目視できる程、開きすぎた従順下品穴のアクメ天国譚。', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(130, $movie['duration']);
        self::assertSame([['external_id' => '859341', 'name' => '坂本りお', 'name_japanese' => '坂本りお', 'url' => 'https://mko-labo.net/actress/detail/859341', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '807589', 'name' => '美波こづえ', 'name_japanese' => '美波こづえ', 'url' => 'https://mko-labo.net/actress/detail/807589', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []]], $movie['performers']);
        self::assertSame(['メイド', 'イラマチオ', '淫乱・ハード系', 'M女', 'アナルセックス'], $movie['tags']);
        self::assertSame([['url' => 'https://cdn.up-timely.com/image/16/content/88346/jmlWIhWomBWssti31t4DFNJ6ebbomEDB5ylTgDI3.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/JsyM2HsNLqOUdSpdwBF339C13Xt3sGp8UGWxKVe5.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/hmYB08PbpIftjwB3YcZ5plUmcurIWsQFPrEdCZ95.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/XMUDKY6rzN7ItYZ85wzRmV6AkqqHTuFtqgBZx2UF.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/eecewak75mWxbU5dVd5Prm8CRwFdBzwtVsyDYF44.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/XVDQsAS3i0EYN52myfcQ6TgSJpynUrMX02vHLUJ3.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/L2WJKX19BGr2YrX3tJDQvjQLcTMcjYTsF5Z6jfip.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/7ll8dTWeLOUijZ3dXvdlhkQclFMXK3K4KhRmeMLP.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/r4ybV5zbqJVDHtoJ4o25fl9L1YLIOyUJzBdZv1Pa.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/mdAdhZ64kBUO1ECJuO3PZJLVN1Z3oHW86zR3oifu.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/16/content/88346/Be0bekBzw3d4Oev1Yv0eytT1G5gx3gG2Xo1VViY2.jpg', 'thumbnail_url' => null, 'metadata' => []]], $movie['screenshots']);
        self::assertSame('MKO LABO', $movie['metadata']['maker']);
        self::assertSame([['name' => 'メイド', 'url' => 'https://mko-labo.net/works/list/genre/9069', 'external_id' => '9069'], ['name' => 'イラマチオ', 'url' => 'https://mko-labo.net/works/list/genre/459', 'external_id' => '459'], ['name' => '淫乱・ハード系', 'url' => 'https://mko-labo.net/works/list/genre/411', 'external_id' => '411'], ['name' => 'M女', 'url' => 'https://mko-labo.net/works/list/genre/9154', 'external_id' => '9154'], ['name' => 'アナルセックス', 'url' => 'https://mko-labo.net/works/list/genre/482', 'external_id' => '482']], $movie['metadata']['genres']);
        self::assertSame([['name' => 'アナルメイドコレクション', 'url' => 'https://mko-labo.net/works/list/series/2618', 'external_id' => '2618']], $movie['metadata']['series']);
        self::assertSame([['name' => '2026年11月24日', 'url' => 'https://mko-labo.net/works/list/date/2026-11-24', 'external_id' => '2026-11-24']], $movie['metadata']['release_links']);
        self::assertSame('イナバール', $movie['metadata']['director']);
        self::assertSame([['format' => 'DVD', 'code' => 'MISM459', 'duration' => 130, 'price' => '4,598円（税込）']], $movie['metadata']['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/SAl4IgRX1N1WW3N8Q7LWGJft6rNLj6QBjky4dqU2UxhAVFv8C_grMQGOuqcIXS/mism00459dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88346/sample_video_image_file/pQ0nhGKpBVT40ywUXaH0zVXiO9qGgjlj0b81Vend.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame([['name' => '動画を購入 ダウンロード+ストリーミング', 'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=mism00459/WILLaffi-046', 'external_id' => 'WILLaffi-046'], ['name' => 'DVDを購入 定価 4,598円（税込）', 'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=mism459/WILLaffi-046', 'external_id' => 'WILLaffi-046']], $movie['metadata']['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $movie['metadata']['release_note']);
        self::assertSame('女優 坂本りお 美波こづえ', $movie['metadata']['source_fields']['女優']);
        self::assertSame('発売日 2026年11月24日', $movie['metadata']['source_fields']['発売日']);
        self::assertSame('シリーズ アナルメイドコレクション', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('ジャンル メイド イラマチオ 淫乱・ハード系 M女 アナルセックス', $movie['metadata']['source_fields']['ジャンル']);
        self::assertSame('監督 イナバール', $movie['metadata']['source_fields']['監督']);
        self::assertSame('品番 DVDMISM459', $movie['metadata']['source_fields']['品番']);
        self::assertSame('収録時間 DVD130分', $movie['metadata']['source_fields']['収録時間']);
        self::assertSame('価格 DVD4,598円（税込）', $movie['metadata']['source_fields']['価格']);
    }

    public function test_actress_profile_retains_every_labelled_row(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('mko/actress-859341.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/859341'));
        $performer = $result->meta['performer'];
        self::assertSame(self::BASE . '/actress/detail/859341', $result->url);
        self::assertSame('859341', $performer['external_id']);
        self::assertSame('SAKAMOTO　RIO', $performer['name']);
        self::assertSame('坂本りお', $performer['name_japanese']);
        self::assertSame('https://mko-labo.net/actress/detail/859341', $performer['url']);
        self::assertSame('https://cdn.up-timely.com/image/16/actress_main/859341/aYTvVVZdwGvTtypeIUeOSUozAojePcHiyNyHPJE7.jpg', $performer['profile_image_url']);
        self::assertNull($performer['birth_date_raw']);
        self::assertNull($performer['height_raw']);
        self::assertNull($performer['size_raw']);
        self::assertSame(['坂本りお', 'SAKAMOTO　RIO'], $performer['aliases']);
        self::assertSame([], $performer['tags']);
        self::assertSame([], $performer['raw_profile']);
        self::assertSame(['https://cdn.up-timely.com/image/16/actress_main/859341/aYTvVVZdwGvTtypeIUeOSUozAojePcHiyNyHPJE7.jpg'], $performer['metadata']['images']);
        self::assertSame([], $performer['metadata']['profile_links']);
        self::assertSame([], $performer['metadata']['social_links']);
        self::assertSame([['name' => '肛門W発狂 真：従順アナルメイドコレクション', 'url' => 'https://mko-labo.net/works/detail/MISM459', 'external_id' => 'MISM459', 'cover_url' => 'https://cdn.up-timely.com/image/16/content/88346/ZFipGhBiuc3tTQIj4g1P8IXgvhC3hObFT4J0d3uS.jpg'], ['name' => 'えむっ娘ラボ十周年記念。極限狂喜。雌穴12ホール', 'url' => 'https://mko-labo.net/works/detail/MISM457', 'external_id' => 'MISM457', 'cover_url' => 'https://cdn.up-timely.com/image/16/content/86866/Ypj4q51k1DItR4SYWXK6pK1fAUlaJ5Z2nNruVnU7.jpg'], ['name' => '穏やかなケツ穴強制開花。夢の中へと連れて行くぶっ壊れ激太30cm 極長100cm ALLズブ挿れ。 華麗なる肛門鍛錬エクスタシー', 'url' => 'https://mko-labo.net/works/detail/MISM396', 'external_id' => 'MISM396', 'cover_url' => 'https://cdn.up-timely.com/image/16/content/80902/uoaYtlIAYJpzoo5uVDF3tKFFz6n5BQelIOMnYTGM.jpg'], ['name' => '肛門喉奥膣穴 欲張り3ツ穴マゾファッカー', 'url' => 'https://mko-labo.net/works/detail/MISM389', 'external_id' => 'MISM389', 'cover_url' => 'https://cdn.up-timely.com/image/16/content/80451/bcNbLOEnbCBNCnNNiSWFINq8Y9dS4FtDvi50X9Eo.jpg']], $performer['metadata']['works']);
        self::assertSame('全4作品中 1 〜 4 タイトルを表示', $performer['metadata']['work_range']);
    }

    public function test_direct_listing_retains_card_fields_and_ensemble_counts(): void
    {
        $result = (new Listing($this->clientWithFixture('mko/list-2026-11-24.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-24'));
        self::assertCount(5, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('MISM459', $movie['external_id']);
        self::assertSame('肛門W発狂 真：従順アナルメイドコレクション', $movie['title']);
        self::assertSame('MISM459', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88346/ZFipGhBiuc3tTQIj4g1P8IXgvhC3hObFT4J0d3uS.jpg', $movie['cover_url']);
        self::assertNull($movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame([['external_id' => '859341', 'name' => '坂本りお', 'name_japanese' => '坂本りお', 'url' => 'https://mko-labo.net/actress/detail/859341', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '807589', 'name' => '美波こづえ', 'name_japanese' => '美波こづえ', 'url' => 'https://mko-labo.net/actress/detail/807589', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []]], $movie['performers']);
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame('MKO LABO', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年11月24日', $movie['metadata']['search_key']);
        self::assertSame('全5作品中 1 〜 5 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('MISM469', $result->items[1]->meta['movie']['code']);
        self::assertSame('0.1mm小さな尻穴を70cm大きく拡張アクメローズ 極肛門マリンバ娘', $result->items[1]->meta['movie']['title']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88344/XdX3rB8R5YM0NDib8dF5FnOJSgnUqbDofefmfT2N.jpg', $result->items[1]->meta['movie']['cover_url']);
        self::assertSame('MISM475', $result->items[2]->meta['movie']['code']);
        self::assertSame('【もっと身長伸びるかなぁ。】大量ゲロ喰いイラマ子女。', $result->items[2]->meta['movie']['title']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88347/us3aZRxPjBPYLAVKoe4mjadsZ5hLLZXO4wnnixBL.jpg', $result->items[2]->meta['movie']['cover_url']);
        self::assertSame('MISM470', $result->items[3]->meta['movie']['code']);
        self::assertSame('アナル喉奥マ○コ 最狂贅沢3穴フルコース 尻穴で全て喰い尽くし爆快感ストックして極ポッカリ展示会。加速イラマで喉奥プレス微笑み失神オートクチュール昇天。一般アパレル店員 もねさん', $result->items[3]->meta['movie']['title']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88345/8merHa5r7vKteMi33zNGPtZVYCJZO2rvQ06pMRA9.jpg', $result->items[3]->meta['movie']['cover_url']);
        self::assertSame('EMLB097', $result->items[4]->meta['movie']['code']);
        self::assertSame('肛門喉奥膣穴 上から犯すか？下から犯すか？ マゾ穴3同時責めで・・・んごべばぁ！状態になるまで性感帯をほじくり犯す3穴狂乱トライアングル昇天祭BEST', $result->items[4]->meta['movie']['title']);
        self::assertSame('https://cdn.up-timely.com/image/16/content/88348/eKTfvVCEPSxZuBIclKCfhd9lOOFwpQ6CgtnF0jat.jpg', $result->items[4]->meta['movie']['cover_url']);
        self::assertSame(4, $result->items[4]->meta['movie']['metadata']['performer_count']);
        self::assertSame([], $result->items[4]->meta['movie']['performers']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_date_index_declares_follow_up_and_paginates_release_groups(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('mko/dates.html')],
            self::BASE . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('mko/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(5, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026年11月24日 作品数：5', $group['name']);
        self::assertSame('2026-11-24', $group['date']);
        self::assertSame('2026-11-24', $group['external_id']);
        self::assertSame(5, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-24', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(81, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }
}
