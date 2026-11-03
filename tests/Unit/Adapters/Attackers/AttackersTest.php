<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Attackers;

use JOOservices\CrawlerX\Adapters\Attackers\AttackersCrawler;
use JOOservices\CrawlerX\Adapters\Attackers\Site;
use JOOservices\CrawlerX\Adapters\S1\Site as S1Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class AttackersTest extends TestCase
{
    private const BASE = 'https://attackers.net';

    private const CDN = 'https://cdn.up-timely.com/image/15/';

    public function test_drama_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('ADN818');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/ADN818', $result->url);
        self::assertSame('ADN818', $movie['external_id']);
        self::assertSame('ADN818', $movie['code']);
        self::assertSame('「妻がしてくれないから、お義姉さん抱かせてくれませんか？」一糸まとわぬ姿で求め合う、密着全裸セックス。', $movie['title']);
        self::assertStringStartsWith('結婚して５年、いまだ新婚のように仲のいい', $movie['description']);
        self::assertStringEndsWith('回避できるならと彩春は体を許してしまい。', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(self::CDN . 'content/88433/cCCKX52qU9kxE7tBOsqQsFvhqJAJ5Y3FRkPnwzlA.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'content/88433/AhNGC1m7sbdsTBpJQQFWOp4l0j4kxKJ9xb8VXPsC.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('347922', $movie['performers'][0]['external_id']);
        self::assertSame('夏目彩春', $movie['performers'][0]['name']);
        self::assertSame('夏目彩春', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/347922', $movie['performers'][0]['url']);
        self::assertSame(['人妻・主婦', '寝取り・寝取られ・ＮＴＲ', '中出し', 'スレンダー', '不倫'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('ATTACKERS', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => '大人のドラマ', 'url' => self::BASE . '/works/list/label/9455', 'external_id' => '9455']], $meta['label']);
        self::assertSame([['name' => '2026年12月1日', 'url' => self::BASE . '/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $meta['release_links']);
        self::assertCount(5, $meta['genres']);
        self::assertSame('205', $meta['genres'][0]['external_id']);
        self::assertSame(self::BASE . '/works/list/genre/383', $meta['genres'][4]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'ADN818', 'duration' => 120, 'price' => '4,598円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/En55PjJQk90Ka4x7zmSHI2lFt12u9rLLt5FLbJIeLyo1vYIc4M-Yr_ghF-X-D/adn00818dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . 'content/88433/sample_video_image_file/syLvgLYVfwW2maUKW8OvWSAWdigTew6wwkqKdZyn.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=adn00818/WILLaffi-010', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 4,598円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('レーベル 大人のドラマ', $meta['source_fields']['レーベル']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('監督', $meta['source_fields']['監督']);
        self::assertSame('価格 DVD4,598円（税込）', $meta['source_fields']['価格']);
        self::assertCount(9, $meta['source_fields']);
        self::assertStringNotContainsString('ATKD450', (string) json_encode($movie));
    }

    public function test_detail_with_director_and_other_label(): void
    {
        $movie = $this->detail('SAME712')->meta['movie'];
        self::assertSame('SAME712', $movie['code']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertStringStartsWith('執拗な嫌がらせを繰り返すライバル校から、', $movie['description']);
        self::assertSame('853705', $movie['performers'][0]['external_id']);
        self::assertSame('天宮花南', $movie['performers'][0]['name']);
        self::assertSame(['女子校生', '部活・マネージャー', '3P、4P', 'ドラマ', '巨乳'], $movie['tags']);
        self::assertSame('きとるね川口', $movie['metadata']['director']);
        self::assertSame('2754', $movie['metadata']['label'][0]['external_id']);
        self::assertSame('死夜悪', $movie['metadata']['label'][0]['name']);
        self::assertSame('4,598円（税込）', $movie['metadata']['formats'][0]['price']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame(self::CDN . 'content/88442/8KoqFHUiIIGQyWXvR9EMPsR46oRFxnfd3YozPabv.jpg', $movie['cover_url']);
        self::assertStringEndsWith('same00712dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('監督 きとるね川口', $movie['metadata']['source_fields']['監督']);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('attackers/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('attackers/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(22, $result->items);
        self::assertSame(self::BASE . '/works/detail/ADN818', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('ADN818', $movie['external_id']);
        self::assertSame('ADN818', $movie['code']);
        self::assertSame('「妻がしてくれないから、お義姉さん抱かせてくれませんか？」一糸まとわぬ姿で求め合う、密着全裸セックス。', $movie['title']);
        self::assertSame(self::CDN . 'content/88433/6fWl7UW9qYzBqZw7Xs192AIdWz5vdLmJnn66F5ou.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame('347922', $movie['performers'][0]['external_id']);
        self::assertSame('夏目彩春', $movie['performers'][0]['name']);
        self::assertSame(22, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-12-01', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $movie['metadata']['release_group']['url']);
        self::assertSame('全22作品中 1 〜 22 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertNull($movie['metadata']['performer_count']);
        $compilation = $result->items[20]->meta['movie'];
        self::assertSame('ATKD450', $compilation['external_id']);
        self::assertSame('寝取られ不倫ドラマ傑作選 35作品16時間BEST', $compilation['title']);
        self::assertSame([], $compilation['performers']);
        self::assertSame(35, $compilation['metadata']['performer_count']);
        self::assertSame('ATKD453', $result->items[21]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(118, $result->pagination->lastPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates(): void
    {
        $url = self::BASE . '/works/list/genre/128';
        $result = (new Listing($this->clientWithFixture('attackers/list-genre-128.html'), $this->config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/ADN818', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('ATID712', $movie['external_id']);
        self::assertSame('白峰ミウ', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('中出し', $movie['metadata']['search_key']);
        self::assertSame('全543作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('ATKD447', $result->items[29]->meta['movie']['external_id']);
        self::assertSame(20, $result->items[29]->meta['movie']['metadata']['performer_count']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('attackers/actress-347922.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/347922'));
        $person = $result->meta['performer'];
        self::assertSame('347922', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/347922', $person['url']);
        self::assertSame('NATSUME IROHA', $person['name']);
        self::assertSame('夏目彩春', $person['name_japanese']);
        self::assertSame(['夏目彩春', 'NATSUME IROHA'], $person['aliases']);
        self::assertSame('1984-06-20', $person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertSame('B85cm (-) W57cm H82cm', $person['size_raw']);
        self::assertSame(['誕生日' => '1984年6月20日', '3サイズ' => 'B85cm (-) W57cm H82cm', '趣味' => '旅行'], $person['raw_profile']);
        self::assertSame(self::CDN . 'actress_main/347922/4ODzbeg3I4RH11t8SiLb9s6PET0k4l7npLiB9Jpf.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('ATKD450', $person['metadata']['works'][0]['external_id']);
        self::assertSame('ADN818', $person['metadata']['works'][1]['external_id']);
        self::assertSame(self::BASE . '/works/detail/ADN818', $person['metadata']['works'][1]['url']);
        self::assertSame('「妻がしてくれないから、お義姉さん抱かせてくれませんか？」一糸まとわぬ姿で求め合う、密着全裸セックス。', $person['metadata']['works'][1]['name']);
        self::assertSame(self::CDN . 'content/88433/6fWl7UW9qYzBqZw7Xs192AIdWz5vdLmJnn66F5ou.jpg', $person['metadata']['works'][1]['cover_url']);
        self::assertSame('全225作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('attackers');
        self::assertInstanceOf(AttackersCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-12-01' => CrawlType::Listing,
            '/works/list/genre/128' => CrawlType::Listing,
            '/works/list/label/9455' => CrawlType::Listing,
            '/works/list/series/1' => CrawlType::Listing,
            '/works/detail/ADN818' => CrawlType::Detail,
            '/actress/detail/347922' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('attackers', $crawler->name());
    }

    public function test_shared_base_leaves_s1_output_unchanged(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), S1Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        self::assertArrayNotHasKey('label', $result->meta['movie']['metadata']);
        self::assertSame('イナバール', $result->meta['movie']['metadata']['director']);
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('attackers/detail-' . $code . '.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function config(): SiteConfig
    {
        return Site::config();
    }
}
