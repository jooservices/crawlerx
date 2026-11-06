<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\BeFreeBe;

use JOOservices\CrawlerX\Adapters\BeFreeBe\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class BeFreeBeTest extends TestCase
{
    private const BASE = 'https://befreebe.com';

    public function test_detail_retains_every_labelled_row_and_excludes_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('befreebe/detail-BF775.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/BF775'));
        $movie = $result->meta['movie'];
        self::assertSame('https://befreebe.com/works/detail/BF775', $result->url);
        self::assertSame('BF775', $movie['external_id']);
        self::assertSame('家庭教師の中出し授業ウルトラベスト480分', $movie['title']);
        self::assertSame('BF775', $movie['code']);
        self::assertSame('https://cdn.up-timely.com/image/13/content/88427/XwbzP2KQwCEWezdIOYoT94q7rPR5LdGj9kUvYDzU.jpg', $movie['cover_url']);
        self::assertSame('BeFree完全監修！これが最後だ！永久保存版！！大人気レジェンドシリーズ「家庭教師の中出し授業」総集編の最終弾が発売決定！「ほんとは君とエッチしたかったの！先生が初めての相手じゃダメかな？」「勉強頑張ったね。だから、先生の中に出していいよ…」貴方もきらめく青春の１ページを共有してください！！', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(480, $movie['duration']);
        self::assertSame([['external_id' => '291929', 'name' => '椎名ゆな', 'name_japanese' => '椎名ゆな', 'url' => 'https://befreebe.com/actress/detail/291929', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '292471', 'name' => '芦名未帆', 'name_japanese' => '芦名未帆', 'url' => 'https://befreebe.com/actress/detail/292471', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '295794', 'name' => '吉咲あんり', 'name_japanese' => '吉咲あんり', 'url' => 'https://befreebe.com/actress/detail/295794', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '284974', 'name' => 'つぼみ', 'name_japanese' => 'つぼみ', 'url' => 'https://befreebe.com/actress/detail/284974', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '293988', 'name' => '愛田奈々', 'name_japanese' => '愛田奈々', 'url' => 'https://befreebe.com/actress/detail/293988', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '296812', 'name' => '桜井あゆ', 'name_japanese' => '桜井あゆ', 'url' => 'https://befreebe.com/actress/detail/296812', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '290053', 'name' => '大槻ひびき', 'name_japanese' => '大槻ひびき', 'url' => 'https://befreebe.com/actress/detail/290053', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '298825', 'name' => '穂高ゆうき', 'name_japanese' => '穂高ゆうき', 'url' => 'https://befreebe.com/actress/detail/298825', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '296596', 'name' => '江波りゅう', 'name_japanese' => '江波りゅう', 'url' => 'https://befreebe.com/actress/detail/296596', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []], ['external_id' => '291260', 'name' => '波多野結衣', 'name_japanese' => '波多野結衣', 'url' => 'https://befreebe.com/actress/detail/291260', 'profile_image_url' => null, 'birth_date_raw' => null, 'height_raw' => null, 'size_raw' => null, 'aliases' => [], 'tags' => [], 'raw_profile' => [], 'metadata' => []]], $movie['performers']);
        self::assertSame(['中出し', '家庭教師', '痴女', '4時間以上作品', 'ベスト・総集編'], $movie['tags']);
        self::assertSame([['url' => 'https://cdn.up-timely.com/image/13/content/88427/XwbzP2KQwCEWezdIOYoT94q7rPR5LdGj9kUvYDzU.jpg', 'thumbnail_url' => null, 'metadata' => []]], $movie['screenshots']);
        self::assertSame('BeFreeBe', $movie['metadata']['maker']);
        self::assertSame([['name' => '中出し', 'url' => 'https://befreebe.com/works/list/genre/128', 'external_id' => '128'], ['name' => '家庭教師', 'url' => 'https://befreebe.com/works/list/genre/371', 'external_id' => '371'], ['name' => '痴女', 'url' => 'https://befreebe.com/works/list/genre/218', 'external_id' => '218'], ['name' => '4時間以上作品', 'url' => 'https://befreebe.com/works/list/genre/5052', 'external_id' => '5052'], ['name' => 'ベスト・総集編', 'url' => 'https://befreebe.com/works/list/genre/5009', 'external_id' => '5009']], $movie['metadata']['genres']);
        self::assertSame([], $movie['metadata']['series']);
        self::assertSame([['name' => 'BeFree', 'url' => 'https://befreebe.com/works/list/label/7964', 'external_id' => '7964']], $movie['metadata']['label']);
        self::assertSame([['name' => '2026年12月1日', 'url' => 'https://befreebe.com/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $movie['metadata']['release_links']);
        self::assertSame(null, $movie['metadata']['director']);
        self::assertSame([['format' => 'DVD', 'code' => 'BF775', 'duration' => 480, 'price' => '4,200円（税込）']], $movie['metadata']['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/ES87HxZchhrjE8CgFR-tlTn5x-7Vw0IgzW368Pe3oDV20gYjzIqMPgPIi_Yw/bf00775dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/13/content/88427/sample_video_image_file/E0ofRKpYWn1AqmmzeLAYSvBpO0PuUCR52ddrpJeS.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame([['name' => '動画を購入 ダウンロード+ストリーミング', 'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=bf00775/WILLaffi-024', 'external_id' => 'WILLaffi-024'], ['name' => 'DVDを購入 定価 4,200円（税込）', 'url' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=bf775/WILLaffi-024', 'external_id' => 'WILLaffi-024']], $movie['metadata']['purchase_links']);
        self::assertSame('※配信日と発売日が異なる場合があります', $movie['metadata']['release_note']);
        self::assertSame('女優 椎名ゆな 芦名未帆 吉咲あんり つぼみ 愛田奈々 桜井あゆ 大槻ひびき 穂高ゆうき 江波りゅう 波多野結衣', $movie['metadata']['source_fields']['女優']);
        self::assertSame('発売日 2026年12月1日', $movie['metadata']['source_fields']['発売日']);
        self::assertSame('シリーズ', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('レーベル BeFree', $movie['metadata']['source_fields']['レーベル']);
        self::assertSame('ジャンル 中出し 家庭教師 痴女 4時間以上作品 ベスト・総集編', $movie['metadata']['source_fields']['ジャンル']);
        self::assertSame('監督 ---', $movie['metadata']['source_fields']['監督']);
        self::assertSame('品番 DVDBF775', $movie['metadata']['source_fields']['品番']);
        self::assertSame('収録時間 DVD480分', $movie['metadata']['source_fields']['収録時間']);
        self::assertSame('価格 DVD4,200円（税込）', $movie['metadata']['source_fields']['価格']);
    }

    public function test_actress_profile_retains_every_labelled_row(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('befreebe/actress-290053.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/290053'));
        $performer = $result->meta['performer'];
        self::assertSame('https://befreebe.com/actress/detail/290053', $result->url);
        self::assertSame('290053', $performer['external_id']);
        self::assertSame('OTSUKI HIBIKI', $performer['name']);
        self::assertSame('大槻ひびき', $performer['name_japanese']);
        self::assertSame('https://befreebe.com/actress/detail/290053', $performer['url']);
        self::assertSame('https://cdn.up-timely.com/image/13/actress_main/290053/Lpgk5QWF4CXsoxh3KOEVKMXZ0wZI17Jhjt8GGMfe.jpeg', $performer['profile_image_url']);
        self::assertSame('1988-02-21', $performer['birth_date_raw']);
        self::assertSame('162cm', $performer['height_raw']);
        self::assertSame('B88cm (E) W57cm H85cm', $performer['size_raw']);
        self::assertSame(['大槻ひびき', 'OTSUKI HIBIKI'], $performer['aliases']);
        self::assertSame([], $performer['tags']);
        self::assertSame('1988年2月21日', $performer['raw_profile']['誕生日']);
        self::assertSame('162cm', $performer['raw_profile']['身長']);
        self::assertSame('B88cm (E) W57cm H85cm', $performer['raw_profile']['3サイズ']);
        self::assertSame(['https://cdn.up-timely.com/image/13/actress_main/290053/Lpgk5QWF4CXsoxh3KOEVKMXZ0wZI17Jhjt8GGMfe.jpeg'], $performer['metadata']['images']);
        self::assertSame([], $performer['metadata']['profile_links']);
        self::assertSame([], $performer['metadata']['social_links']);
        self::assertSame('全17作品中 1 〜 12 タイトルを表示', $performer['metadata']['work_range']);
        self::assertSame([['name' => '家庭教師の中出し授業ウルトラベスト480分', 'url' => 'https://befreebe.com/works/detail/BF775', 'external_id' => 'BF775', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/88427/gVhiKne4UVdjqPjCLKmoTXOsYHDKqZgE8Uadb690.jpg'], ['name' => 'レジェンド女優BEST480分', 'url' => 'https://befreebe.com/works/detail/BF742', 'external_id' => 'BF742', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/80515/BfnwonQK2Sf23g7r1en3kFZsWrsGHPRhDThGu7Wx.jpg'], ['name' => 'ニーハイソックス8時間BEST', 'url' => 'https://befreebe.com/works/detail/BF538', 'external_id' => 'BF538', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/30049/BF538_1.jpg'], ['name' => '亀頭チロチロの瞬間を集めたフェラチオBEST', 'url' => 'https://befreebe.com/works/detail/BF481', 'external_id' => 'BF481', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29992/BF481_1.jpg'], ['name' => '綺麗なお姉さん限定!美顔射祭!', 'url' => 'https://befreebe.com/works/detail/BF452', 'external_id' => 'BF452', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29963/BF452_1.jpg'], ['name' => 'BeFree2015 CompleteBEST49タイトル8時間', 'url' => 'https://befreebe.com/works/detail/BF440', 'external_id' => 'BF440', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29951/BF440_1.jpg'], ['name' => 'フェラチオ大好きお姉さん達の口内射精34発8時間', 'url' => 'https://befreebe.com/works/detail/BF441', 'external_id' => 'BF441', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29952/BF441_1.jpg'], ['name' => '中出し直前の激ピストンFUCK38発射8時間!', 'url' => 'https://befreebe.com/works/detail/BF398', 'external_id' => 'BF398', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29909/BF398_1.jpg'], ['name' => '中出し着衣SEX8時間', 'url' => 'https://befreebe.com/works/detail/BF393', 'external_id' => 'BF393', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29904/BF393_1.jpg'], ['name' => '働くお姉さんの口内発射39発480分', 'url' => 'https://befreebe.com/works/detail/BF380', 'external_id' => 'BF380', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29891/BF380_1.jpg'], ['name' => '先生と生徒の禁断の性交8時間', 'url' => 'https://befreebe.com/works/detail/BF374', 'external_id' => 'BF374', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29885/BF374_1.jpg'], ['name' => 'BeFree2014年全48タイトル8時間BEST', 'url' => 'https://befreebe.com/works/detail/BF368', 'external_id' => 'BF368', 'cover_url' => 'https://cdn.up-timely.com/image/13/content/29879/BF368_1.jpg']], $performer['metadata']['works']);
    }

    public function test_listing_retains_card_fields(): void
    {
        $result = (new Listing($this->clientWithFixture('befreebe/list-2026-12-01.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-12-01'));
        self::assertCount(2, $result->items);
        self::assertSame('BF775', $result->items[0]->meta['movie']['external_id']);
        self::assertSame('家庭教師の中出し授業ウルトラベスト480分', $result->items[0]->meta['movie']['title']);
        self::assertSame('BF775', $result->items[0]->meta['movie']['code']);
        self::assertSame('https://cdn.up-timely.com/image/13/content/88427/gVhiKne4UVdjqPjCLKmoTXOsYHDKqZgE8Uadb690.jpg', $result->items[0]->meta['movie']['cover_url']);
        self::assertSame(null, $result->items[0]->meta['movie']['description']);
        self::assertSame('2026-12-01', $result->items[0]->meta['movie']['date']);
        self::assertSame(null, $result->items[0]->meta['movie']['duration']);
        self::assertSame([], $result->items[0]->meta['movie']['performers']);
        self::assertSame([], $result->items[0]->meta['movie']['tags']);
        self::assertSame([], $result->items[0]->meta['movie']['screenshots']);
        self::assertSame('BeFreeBe', $result->items[0]->meta['movie']['metadata']['maker']);
        self::assertSame(10, $result->items[0]->meta['movie']['metadata']['performer_count']);
        self::assertSame(null, $result->items[0]->meta['movie']['metadata']['badge']);
        self::assertSame(null, $result->items[0]->meta['movie']['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年12月1日', $result->items[0]->meta['movie']['metadata']['search_key']);
        self::assertSame('全2作品中 1 〜 2 タイトルを表示', $result->items[0]->meta['movie']['metadata']['listing_range']);
        self::assertSame('BF776', $result->items[1]->meta['movie']['external_id']);
        self::assertSame('酔った勢いで誘惑してくる美女たち', $result->items[1]->meta['movie']['title']);
        self::assertSame('BF776', $result->items[1]->meta['movie']['code']);
        self::assertSame('https://cdn.up-timely.com/image/13/content/88426/vCrpQt2ktsgENZ8WXJ2OAW74AJgZhg5xcygeocro.jpg', $result->items[1]->meta['movie']['cover_url']);
        self::assertSame(null, $result->items[1]->meta['movie']['description']);
        self::assertSame('2026-12-01', $result->items[1]->meta['movie']['date']);
        self::assertSame(null, $result->items[1]->meta['movie']['duration']);
        self::assertSame([], $result->items[1]->meta['movie']['performers']);
        self::assertSame([], $result->items[1]->meta['movie']['tags']);
        self::assertSame([], $result->items[1]->meta['movie']['screenshots']);
        self::assertSame('BeFreeBe', $result->items[1]->meta['movie']['metadata']['maker']);
        self::assertSame(5, $result->items[1]->meta['movie']['metadata']['performer_count']);
        self::assertSame(null, $result->items[1]->meta['movie']['metadata']['badge']);
        self::assertSame(null, $result->items[1]->meta['movie']['metadata']['release_group']);
        self::assertSame('発売日検索結果：2026年12月1日', $result->items[1]->meta['movie']['metadata']['search_key']);
        self::assertSame('全2作品中 1 〜 2 タイトルを表示', $result->items[1]->meta['movie']['metadata']['listing_range']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_date_index_declares_follow_up_and_paginates_release_groups(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('befreebe/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('befreebe/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(2, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026年12月1日 作品数：2', $group['name']);
        self::assertSame('2026-12-01', $group['date']);
        self::assertSame('2026-12-01', $group['external_id']);
        self::assertSame(2, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(72, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }
}
