<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\PremiumBeauty;

use JOOservices\CrawlerX\Adapters\PremiumBeauty\PremiumBeautyCrawler;
use JOOservices\CrawlerX\Adapters\PremiumBeauty\Site;
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

final class PremiumBeautyTest extends TestCase
{
    private const BASE = 'https://premium-beauty.com';

    private const CDN = 'https://cdn.up-timely.com/image/8';

    public function test_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('PRED896');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/PRED896', $result->url);
        self::assertSame('PRED896', $movie['external_id']);
        self::assertSame('PRED896', $movie['code']);
        self::assertSame('超焦らし魔なカノジョは中々挿れさせてくれないのにどエロいベロチュウや手コキ超絶テクニックで責め続け挿入した途端…精子が枯れるまで何度も求める淫乱ビッチ化する', $movie['title']);
        self::assertStringStartsWith('焦らし魔でからかい上手で、めちゃエロい彼女は', $movie['description']);
        self::assertStringEndsWith('ボクらM男はそんな夢を見てる…。', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(self::CDN . '/content/88165/veWXe0b1oP9TNm70OXYTI57JIB3TSJyu3eewP818.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '/content/88165/x9Ge8lhAXSNdxOKLAVWxzkVuxdIsopdqOsP3zt1B.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('844918', $movie['performers'][0]['external_id']);
        self::assertSame('小野坂ゆいか', $movie['performers'][0]['name']);
        self::assertSame('小野坂ゆいか', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/844918', $movie['performers'][0]['url']);
        self::assertSame(['痴女', 'キス・接吻', '手コキ', '巨乳', '中出し'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('Premium Beauty', $meta['maker']);
        self::assertSame('宝瀬博教', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => 'エレガンス', 'url' => self::BASE . '/works/list/label/9787', 'external_id' => '9787']], $meta['label']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertCount(5, $meta['genres']);
        self::assertSame('218', $meta['genres'][0]['external_id']);
        self::assertSame(self::BASE . '/works/list/genre/128', $meta['genres'][4]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'PRED896', 'duration' => 120, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/Fz8kASccnxmttxIF5XqcfkhzYrdOg6bQb9lf0V9ohKXLd-FeSSNgt4lfqFpoCO/pred00896dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . '/content/88165/sample_video_image_file/lRp52sLB0RbXhFgWPqZLrOFK0BH8kxZIX438aicZ.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=pred00896/WILLaffi-032', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 3,498円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('レーベル エレガンス', $meta['source_fields']['レーベル']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertCount(9, $meta['source_fields']);
    }

    public function test_best_compilation_detail_has_all_performers_and_placeholder_director(): void
    {
        $movie = $this->detail('PBD529')->meta['movie'];
        self::assertSame('PBD529', $movie['code']);
        self::assertSame(1380, $movie['duration']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertCount(17, $movie['performers']);
        self::assertSame('854128', $movie['performers'][0]['external_id']);
        self::assertSame('三好佑香', $movie['performers'][0]['name']);
        self::assertSame('宍戸里帆', $movie['performers'][16]['name']);
        self::assertSame(['お姉さん', '女子アナ', '痴女', '中出し', '16時間以上作品'], $movie['tags']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame('6705', $movie['metadata']['label'][0]['external_id']);
        self::assertSame('PREMIUM BEST', $movie['metadata']['label'][0]['name']);
        self::assertSame('3,498円（税込）', $movie['metadata']['formats'][0]['price']);
        self::assertSame('監督 ---', $movie['metadata']['source_fields']['監督']);
        self::assertSame(self::CDN . '/content/88167/CefPwMJqMAghoIRUK1USraRaNZbKvO4gDitcuSZl.jpg', $movie['screenshots'][0]['url']);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('premiumbeauty/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('premiumbeauty/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(8, $result->items);
        self::assertSame(self::BASE . '/works/detail/PRED906', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('PRED906', $movie['external_id']);
        self::assertSame('PRED906', $movie['code']);
        self::assertSame('色白美尻の家事代行お姉さんに即ハメ！僕のチ〇ポを求めて押しかけてきたので何度も種付け中出ししてやった。', $movie['title']);
        self::assertSame(self::CDN . '/content/88162/OsnvBir8rQcnPJKuOEVVS1BYWA2Oge0DlsZwogBc.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame('854128', $movie['performers'][0]['external_id']);
        self::assertSame('三好佑香', $movie['performers'][0]['name']);
        self::assertSame(8, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全8作品中 1 〜 8 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertSame('Premium Beauty', $movie['metadata']['maker']);
        $compilation = $result->items[6]->meta['movie'];
        self::assertSame('PBD529', $compilation['external_id']);
        self::assertSame(17, $compilation['metadata']['performer_count']);
        self::assertSame('PBD530', $result->items[7]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(136, $result->pagination->lastPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates(): void
    {
        $url = self::BASE . '/works/list/genre/218';
        $result = (new Listing($this->clientWithFixture('premiumbeauty/list-genre-218.html'), Site::config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/PRED904', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('PRED896', $movie['external_id']);
        self::assertSame('小野坂ゆいか', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('痴女', $movie['metadata']['search_key']);
        self::assertSame('全412作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $person = $this->actress('800157')->meta['performer'];
        self::assertSame('800157', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/800157', $person['url']);
        self::assertSame('YUZURIHA KAREN', $person['name']);
        self::assertSame('楪カレン', $person['name_japanese']);
        self::assertSame(['楪カレン', 'YUZURIHA KAREN'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertSame('B96cm (H) W58cm H--cm', $person['size_raw']);
        self::assertSame(['3サイズ' => 'B96cm (H) W58cm H--cm', '出身地' => '沖縄県', '趣味' => 'AV鑑賞', '特技' => '三線'], $person['raw_profile']);
        self::assertSame(self::CDN . '/actress_main/800157/fWrsWMnS2snJ1QLww7xfoIrqFX89CWri5nFrBwPP.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('PBD530', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/PBD530', $person['metadata']['works'][0]['url']);
        self::assertSame(self::CDN . '/content/88159/sPHD5seRCW8cwCTdtkI4mrgFbVcDgI1lJXu3iBi3.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('全128作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_actress_without_profile_rows(): void
    {
        $person = $this->actress('844918')->meta['performer'];
        self::assertSame('ONOSAKA YUIKA', $person['name']);
        self::assertSame('小野坂ゆいか', $person['name_japanese']);
        self::assertSame([], $person['raw_profile']);
        self::assertNull($person['size_raw']);
        self::assertSame(self::CDN . '/actress_main/844918/7c8puWJO0i1zpfTFjhkT1Skd2ACt1YEMdnVqKv2L.jpg', $person['profile_image_url']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('PRED896', $person['metadata']['works'][0]['external_id']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('premiumbeauty');
        self::assertInstanceOf(PremiumBeautyCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-17' => CrawlType::Listing,
            '/works/list/genre/218' => CrawlType::Listing,
            '/works/list/label/9787' => CrawlType::Listing,
            '/works/list/series/1' => CrawlType::Listing,
            '/works/detail/PRED896' => CrawlType::Detail,
            '/actress/detail/800157' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('premiumbeauty', $crawler->name());
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('premiumbeauty/detail-' . $code . '.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function actress(string $id): CrawlItemResultDto
    {
        return (new PerformerDetail($this->clientWithFixture('premiumbeauty/actress-' . $id . '.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/' . $id));
    }
}
