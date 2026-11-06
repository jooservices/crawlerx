<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Wanz;

use JOOservices\CrawlerX\Adapters\Wanz\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class WanzTest extends TestCase
{
    private const BASE = 'https://wanz-factory.com';

    public function test_detail_fields_exclude_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('wanz/detail-WAAA716.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/WAAA716'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/WAAA716', $result->url);
        self::assertSame('WAAA716', $movie['external_id']);
        self::assertSame('蛇舌スパイラルM性感 全身リップ吸舐双爆フェラで挟み撃ち中出しFUCKハーレム！', $movie['title']);
        self::assertSame('WAAA716', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/31/content/88495/Mta9BgDeTd3Nr83bip43qgKXd3caEUz9gXVL2M1u.jpg', $movie['cover_url']);
        self::assertSame('【アナコンダ痴女：大槻ひびき×シン蛇舌クイーン：逢沢みゆ初共演！W舐め殺しスパイラルM性感！】長～いベロで全身リップ双爆フェラで挟み撃ち！涎ダラダラ垂らして迫りくる2人が男の性感帯【チ〇ポ・アナル・乳首】を舐め吸い刺激！そしてレロレロ舌を絡ませまくる鬼キッスで男を蹂躙！さらにベロ出しアヘ顔FUCKでザーメン搾り中出し！そのまま勢いは止まらず追撃こねくりネロネロ挟撃連射！最狂トルネード蛇ッ痴ハーレム！', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(150, $movie['duration']);
        self::assertSame([['external_id' => '723349', 'name' => '大槻ひびき', 'name_japanese' => '大槻ひびき', 'url' => 'https://wanz-factory.com/actress/detail/723349', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '832805', 'name' => '逢沢みゆ', 'name_japanese' => '逢沢みゆ', 'url' => 'https://wanz-factory.com/actress/detail/832805', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []]], $movie['performers']);
        self::assertSame(['キス・接吻', 'フェラ', '中出し', 'ハーレム', '痴女'], $movie['tags']);
        self::assertSame([['url' => 'https://cdn.up-timely.com/image/31/content/88495/Mta9BgDeTd3Nr83bip43qgKXd3caEUz9gXVL2M1u.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/HVVs5QDI2gIydDuZnYHdxoMXf9OwfZmD5S30DFwD.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/H5P7jghqqw3PluboXA1hy5oVYgecdNiLZ88gppX1.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/xBVxUG7sbxJ7V3XYh0pW3JECRPAtC9HNrmeLnco7.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/CLAOhMRVCsvegzHEaTkb9FDmfdJOJiB3LIxRsbIa.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/V39MXRhfbUpIFnvwv4RpZx4IZqqdmqk0eRnIJVwS.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/3vVwcJhD0CSfidORhFeUf7TtlXpNfcOjx71sbpFB.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/IrsDvwch9PW7y4emdiXLDsg5Z8Zk8LpT0jWWhFwh.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/lSdeTtpTLt2N5pTZcQvinttjcOdXk4tHEMy73SYG.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/FOovJ8o5fB03NN4hO1xZMAEoc2jdDYSidk6E5Ivh.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/p3RbhmJ5g4edhXgkXIQMxLMFNP7UW5gL23cW8r3q.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/Qj7Ji62hAN0iTUpBUWTkdcfGIS4ahf1Aie5vVJQy.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/5cueT8scomDlASBmlGVSqkU1Xfl2bG9HXUm1NXFv.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/PBTSyNgqLs4gbjcFZcQL27HciUZ7sAHldF1d3LrZ.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/Gf8lTGZv19yEWnYsXGAp7oBAnhE2NdXkOj7NGLr6.jpg', 'thumbnail_url' => null, 'metadata' => []], ['url' => 'https://cdn.up-timely.com/image/31/content/88495/cis6d2upnW4ixvSpL4cdDhyao1L5n5zYU3BtDBPc.jpg', 'thumbnail_url' => null, 'metadata' => []]], $movie['screenshots']);
        self::assertSame('WANZ FACTORY', $movie['metadata']['maker']);
        self::assertSame([['name' => 'キス・接吻', 'url' => 'https://wanz-factory.com/works/list/genre/9163', 'external_id' => '9163'], ['name' => 'フェラ', 'url' => 'https://wanz-factory.com/works/list/genre/113', 'external_id' => '113'], ['name' => '中出し', 'url' => 'https://wanz-factory.com/works/list/genre/128', 'external_id' => '128'], ['name' => 'ハーレム', 'url' => 'https://wanz-factory.com/works/list/genre/476', 'external_id' => '476'], ['name' => '痴女', 'url' => 'https://wanz-factory.com/works/list/genre/218', 'external_id' => '218']], $movie['metadata']['genres']);
        self::assertSame([], $movie['metadata']['series']);
        self::assertSame([['name' => 'WANZ', 'url' => 'https://wanz-factory.com/works/list/label/9402', 'external_id' => '9402']], $movie['metadata']['label']);
        self::assertSame([['name' => '2026年12月1日', 'url' => 'https://wanz-factory.com/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $movie['metadata']['release_links']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame([['format' => 'DVD', 'code' => 'WAAA716', 'duration' => 150, 'price' => '4,200円（税込）']], $movie['metadata']['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/Fy4mDQoAnioxYd22_cC3gf2wNX4H4U7bXzQDlxbOD0FKU9KBkTJjHDHoa3hZYB/waaa00716dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/31/content/88495/sample_video_image_file/pSGhYggW7PSzJkOCMgJgpj4s67Uz8xFw2VsdssJu.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame([['name' => '動画を購入 ダウンロード+ストリーミング', 'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=waaa00716/WILLaffi-048', 'external_id' => 'WILLaffi-048'], ['name' => 'DVDを購入 定価 4,200円（税込）', 'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=waaa716/WILLaffi-048', 'external_id' => 'WILLaffi-048']], $movie['metadata']['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $movie['metadata']['release_note']);
        self::assertSame('女優 大槻ひびき 逢沢みゆ', $movie['metadata']['source_fields']['女優']);
        self::assertSame('発売日 2026年12月1日', $movie['metadata']['source_fields']['発売日']);
        self::assertSame('シリーズ', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('レーベル WANZ', $movie['metadata']['source_fields']['レーベル']);
        self::assertSame('ジャンル キス・接吻 フェラ 中出し ハーレム 痴女', $movie['metadata']['source_fields']['ジャンル']);
        self::assertSame('監督', $movie['metadata']['source_fields']['監督']);
        self::assertSame('品番 DVDWAAA716', $movie['metadata']['source_fields']['品番']);
        self::assertSame('収録時間 DVD150分', $movie['metadata']['source_fields']['収録時間']);
        self::assertSame('価格 DVD4,200円（税込）', $movie['metadata']['source_fields']['価格']);
    }

    public function test_actress_profile_retains_every_labelled_row(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('wanz/actress-723349.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/723349'));
        $performer = $result->meta['performer'];
        self::assertSame(self::BASE . '/actress/detail/723349', $result->url);
        self::assertSame('723349', $performer['external_id']);
        self::assertSame('OTSUKI HIBIKI', $performer['name']);
        self::assertSame('大槻ひびき', $performer['name_japanese']);
        self::assertSame('https://wanz-factory.com/actress/detail/723349', $performer['url']);
        self::assertSame('https://cdn.up-timely.com/image/31/actress_main/723349/CdbHo43FjgO4ksY1Yqqi3nhqXQ2mTmD9Obj0OkQg.jpg', $performer['profile_image_url']);
        self::assertSame('1988-02-21', $performer['birth_date_raw']);
        self::assertSame('162cm', $performer['height_raw']);
        self::assertSame('B88cm (E) W57cm H85cm', $performer['size_raw']);
        self::assertSame(['大槻ひびき', 'OTSUKI HIBIKI'], $performer['aliases']);
        self::assertSame([], $performer['tags']);
        self::assertSame('1988年2月21日', $performer['raw_profile']['誕生日']);
        self::assertSame('162cm', $performer['raw_profile']['身長']);
        self::assertSame('B88cm (E) W57cm H85cm', $performer['raw_profile']['3サイズ']);
        self::assertSame('買い物、お菓子作り', $performer['raw_profile']['趣味']);
        self::assertSame('買い物、お菓子作り', $performer['raw_profile']['特技']);
        self::assertSame(['https://cdn.up-timely.com/image/31/actress_main/723349/CdbHo43FjgO4ksY1Yqqi3nhqXQ2mTmD9Obj0OkQg.jpg'], $performer['metadata']['images']);
        self::assertSame([], $performer['metadata']['profile_links']);
        self::assertSame([], $performer['metadata']['social_links']);
        self::assertSame([['name' => '蛇舌スパイラルM性感 全身リップ吸舐双爆フェラで挟み撃ち中出しFUCKハーレム！', 'url' => 'https://wanz-factory.com/works/detail/WAAA716', 'external_id' => 'WAAA716', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/88495/sboLsEcFdMmqzUh8VNnwICBmYsdQKQByyX7DlALv.jpg'], ['name' => 'コンドームが破れてまさかの生ハメ！超加速するピストンで何度も中出し！', 'url' => 'https://wanz-factory.com/works/detail/WAAA705', 'external_id' => 'WAAA705', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/87998/djCmF67WKbQif56cfJg8XnXnITpLeGRs7bJehBXw.jpg'], ['name' => 'ヤリマン妻がラブホで旦那スワップ ザー汁搾り尽くすセクテク競い合いチ〇ポが持たないハーレム痴女3P', 'url' => 'https://wanz-factory.com/works/detail/WAAA691', 'external_id' => 'WAAA691', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/87515/XYZF1bXbDnrYmUMsWmVU40IbvNleLjhb8i06L1Q8.jpg'], ['name' => '波多野結衣となかまたち。企画いっぱい詰め込んじゃいましたッ！BEST', 'url' => 'https://wanz-factory.com/works/detail/BMW367', 'external_id' => 'BMW367', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/87508/ZUFtOFSRLJknL9GLSU9zsoKEtiUO0Cgjw5d4NihY.jpg'], ['name' => '観ればアナタも追撃ピストンでイカせまくり！How to SEX イッてるってばぁ…！編', 'url' => 'https://wanz-factory.com/works/detail/WAAA684', 'external_id' => 'WAAA684', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/87015/sgaHA9kjxRfezOlB7YVvsMmre1MfeB2iNmmYwB5I.jpg'], ['name' => '週末は胸キュン青春ラブSEX 年上の妻に制服を着せて性欲バキバキときめきリブート発情連射19発中出し！', 'url' => 'https://wanz-factory.com/works/detail/WAAA676', 'external_id' => 'WAAA676', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/86523/7RbndrjgKa2PRZbKN9LjCbG2gZLqWjI6oiFPStuN.jpg'], ['name' => '凄テクW痴女リブート！乳首・亀頭・アナル男の快楽スポットトリプル超快感3点責め！中出し風俗デラックス', 'url' => 'https://wanz-factory.com/works/detail/WAAA663', 'external_id' => 'WAAA663', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/86066/4Sq7yTt6vX146WwHsgN7ucFpOL19gpOXSs53QyvA.jpg'], ['name' => '「こんな年上でいいの…？」女としての自信を失っていた美人妻は一途なバイト男子との不倫中出しに溺れた', 'url' => 'https://wanz-factory.com/works/detail/WAAA650', 'external_id' => 'WAAA650', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/85547/W8KyRTbGTBoLIRxFrwr71u1wXS4CoJtUEoFJcVgG.jpg'], ['name' => '旦那がすぐそばにいるのに若い男を誘い入れ騎乗位でハメ犯す汁まみれ性欲モンスター団地妻リベンジFUCK23発', 'url' => 'https://wanz-factory.com/works/detail/WAAA644', 'external_id' => 'WAAA644', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/85059/mmEw8thfVzuPhbH2DMqPRokjqCuOlOMFEtrPw4EJ.jpg'], ['name' => '顔面特化アナコンダ蛇舌オナサポ射精管理クリニック アナタの見つめてベロベロ舐めシャブりナース唾液ベトベト接吻ちんシコ淫語責め！', 'url' => 'https://wanz-factory.com/works/detail/WAAA637', 'external_id' => 'WAAA637', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/84584/zQ0fCHT5M0jzAk8LWSJEuEsPFtoIGWk0fag1H5xW.jpg'], ['name' => '20年近くチ〇ポを研究した大槻ひびきがじっくり射精コントロール　狂気的快感に導くスロー性感施術　痴女回春メンズエステ', 'url' => 'https://wanz-factory.com/works/detail/WAAA623', 'external_id' => 'WAAA623', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/84063/Tmq7VSYW4UDPmy0536e1xseTVYNBUPYhjrIuGxPn.jpg'], ['name' => '色気ムンムン女上司にバキバキ童貞がバレてしまい…まさかの筆おろし相部屋 またがりベロキス淫語浴びせ杭打ち中出しFUCKで膣射調教を繰り返し一晩中ヤリまくった…', 'url' => 'https://wanz-factory.com/works/detail/WAAA617', 'external_id' => 'WAAA617', 'cover_url' => 'https://cdn.up-timely.com/image/31/content/83562/0SFJi1mavMOTMpj0sdbYb4gyQA2CNuClOxKKg5fA.jpg']], $performer['metadata']['works']);
        self::assertSame('全31作品中 1 〜 12 タイトルを表示', $performer['metadata']['work_range']);
    }

    public function test_direct_listing_retains_card_fields_and_ensemble_counts(): void
    {
        $result = (new Listing($this->clientWithFixture('wanz/list-2026-12-01.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-12-01'));
        self::assertCount(11, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('WAAA716', $movie['external_id']);
        self::assertSame('蛇舌スパイラルM性感 全身リップ吸舐双爆フェラで挟み撃ち中出しFUCKハーレム！', $movie['title']);
        self::assertSame('WAAA716', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/31/content/88495/sboLsEcFdMmqzUh8VNnwICBmYsdQKQByyX7DlALv.jpg', $movie['cover_url']);
        self::assertNull($movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame([['external_id' => '723349', 'name' => '大槻ひびき', 'name_japanese' => '大槻ひびき', 'url' => 'https://wanz-factory.com/actress/detail/723349', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '832805', 'name' => '逢沢みゆ', 'name_japanese' => '逢沢みゆ', 'url' => 'https://wanz-factory.com/actress/detail/832805', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []]], $movie['performers']);
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);
        self::assertSame('WANZ FACTORY', $movie['metadata']['maker']);
        self::assertNull($movie['metadata']['performer_count']);
        self::assertNull($movie['metadata']['badge']);
        self::assertNull($movie['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertSame('全11作品中 1 〜 11 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('BMW371', $result->items[9]->meta['movie']['code']);
        self::assertSame(8, $result->items[9]->meta['movie']['metadata']['performer_count']);
        self::assertSame([], $result->items[9]->meta['movie']['performers']);
        self::assertSame('BMW372', $result->items[10]->meta['movie']['code']);
        self::assertSame(14, $result->items[10]->meta['movie']['metadata']['performer_count']);
        self::assertSame([], $result->items[10]->meta['movie']['performers']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_date_index_declares_follow_up_and_paginates_release_groups(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('wanz/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('wanz/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(11, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026年12月1日 作品数：11', $group['name']);
        self::assertSame('2026-12-01', $group['date']);
        self::assertSame('2026-12-01', $group['external_id']);
        self::assertSame(11, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(227, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }
}
