<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Dasdas;

use JOOservices\CrawlerX\Adapters\Dasdas\DasdasCrawler;
use JOOservices\CrawlerX\Adapters\Dasdas\Site;
use JOOservices\CrawlerX\Adapters\S1\Site as S1Site;
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

final class DasdasTest extends TestCase
{
    private const BASE = 'https://dasdas.jp';

    public function test_best_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('DAZD317');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/DAZD317', $result->url);
        self::assertSame('DAZD317', $movie['external_id']);
        self::assertSame('DAZD317', $movie['code']);
        self::assertSame('プロの舌技でマ〇コを解きほぐすように女を舐め堕とす快感クンニ97連発BEST', $movie['title']);
        self::assertStringStartsWith('ベロベロちゅぱちゅぱ巧みな舌技で舐めイカすクンニ特化BEST！', $movie['description']);
        self::assertStringEndsWith('卑猥な蜜と香りを放ちながら快楽堕ち！！', $movie['description']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame(230, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88355/RDJ7YRIynlCmhX0na7alsRzcRYNpm9ZoOoRoRf4V.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88355/BcoHvLWff7rNm5QFY7Tw0QWMf9O9RytCu6XnL76e.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(40, $movie['performers']);
        self::assertSame('798158', $movie['performers'][0]['external_id']);
        self::assertSame('白峰ミウ', $movie['performers'][0]['name']);
        self::assertSame('白峰ミウ', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/798158', $movie['performers'][0]['url']);
        self::assertSame('865958', $movie['performers'][39]['external_id']);
        self::assertSame('綾瀬舞菜', $movie['performers'][39]['name']);
        self::assertSame(['クンニ', 'アクメ・オーガズム', '指マン', '美少女', 'ベスト・総集編'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('DASDAS', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertCount(5, $meta['genres']);
        self::assertSame(['name' => 'クンニ', 'url' => self::BASE . '/works/list/genre/335', 'external_id' => '335'], $meta['genres'][0]);
        self::assertSame('5009', $meta['genres'][4]['external_id']);
        self::assertSame([['name' => '2026年11月24日', 'url' => self::BASE . '/works/list/date/2026-11-24', 'external_id' => '2026-11-24']], $meta['release_links']);
        self::assertSame([['format' => 'DVD', 'code' => 'DAZD317', 'duration' => 230, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/PA4nPjsAEInKhdTPFev_Ber8RegNaIWjqfSF_lg3akinfTo98rF5Ff4BnkNULI/dazd00317dm.mp4', $meta['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88355/sample_video_image_file/Qux7tXRI1EgGKStQwcA4I3Ekdj3i06lqBkHkjhnp.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=dazd00317/WILLaffi-038', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 3,498円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('監督 ---', $meta['source_fields']['監督']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertSame('収録時間 DVD230分', $meta['source_fields']['収録時間']);
        self::assertSame('品番 DVDDAZD317', $meta['source_fields']['品番']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('発売日 2026年11月24日', $meta['source_fields']['発売日']);
        self::assertStringStartsWith('ジャンル クンニ', $meta['source_fields']['ジャンル']);
        self::assertStringStartsWith('女優 白峰ミウ', $meta['source_fields']['女優']);
        self::assertCount(8, $meta['source_fields']);
        self::assertStringNotContainsString('DSOD137', (string) json_encode($movie));
    }

    public function test_detail_with_director_and_two_performers(): void
    {
        $movie = $this->detail('DSOD137')->meta['movie'];
        self::assertSame('DSOD137', $movie['code']);
        self::assertSame('竿友レズSEX　恥じらいデートからの本気交尾', $movie['title']);
        self::assertStringStartsWith('※2本の竿が淫らに交わる濃厚NHレズセックス', $movie['description']);
        self::assertSame(150, $movie['duration']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88357/TMcEkyHpZO7mzxg4N6eDXOXMXJHdL82Njs7du6K2.jpg', $movie['cover_url']);
        self::assertCount(21, $movie['screenshots']);
        self::assertSame(['869163', '846352'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['柊もみじ', '柊かな'], array_column($movie['performers'], 'name'));
        self::assertSame(['レズ', 'アナルセックス', '3P、4P', 'デート', 'ニューハーフ'], $movie['tags']);
        self::assertSame('真咲南朋', $movie['metadata']['director']);
        self::assertSame('4,598円（税込）', $movie['metadata']['formats'][0]['price']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88357/sample_video_image_file/ZUnjn50BeImFWzkvWt0mpj8TlCL6sIBiJLLTvUza.jpg', $movie['metadata']['sample_video_poster_url']);
        self::assertSame('監督 真咲南朋', $movie['metadata']['source_fields']['監督']);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('dasdas/dates.html')],
            self::BASE . '/works/list/date/2026-11-24' => ['body' => $this->loadFixture('dasdas/list-2026-11-24.html')],
        ]);
        $result = (new Listing($client, $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(13, $result->items);
        self::assertSame(self::BASE . '/works/detail/DSOD128', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('DSOD128', $movie['external_id']);
        self::assertSame('DSOD128', $movie['code']);
        self::assertSame('2.5次元神乳ボディの彼女が俺の親父に寝取られ種付けプレスされていた。', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88356/RTfhDdoGEg41fZBQEOBEcNzqKjmSIb47JJy1Jgrd.jpg', $movie['cover_url']);
        self::assertSame('2026-11-24', $movie['date']);
        self::assertSame('797118', $movie['performers'][0]['external_id']);
        self::assertSame('姫咲はな', $movie['performers'][0]['name']);
        self::assertSame(13, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-24', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-24', $movie['metadata']['release_group']['url']);
        self::assertSame('全13作品中 1 〜 13 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月24日', $movie['metadata']['search_key']);
        self::assertSame('DAZD318', $result->items[12]->meta['movie']['external_id']);
        self::assertSame(11, $result->items[12]->meta['movie']['metadata']['performer_count']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_cards(): void
    {
        $url = self::BASE . '/works/list/genre/335';
        $result = (new Listing($this->clientWithFixture('dasdas/list-genre-335.html'), $this->config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(17, $result->items);
        self::assertSame(self::BASE . '/works/detail/DAZD317', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('DAZD317', $movie['external_id']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88355/jTXa8hj0Sm8TGc0yfsnsRD4dNwnusrAL1aXKPNon.jpg', $movie['cover_url']);
        self::assertSame(40, $movie['metadata']['performer_count']);
        self::assertSame('クンニ', $movie['metadata']['search_key']);
        self::assertSame('全17作品中 1 〜 17 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame(self::BASE . '/works/detail/PLA057', $result->items[16]->url);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('dasdas/actress-798158.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/798158'));
        $person = $result->meta['performer'];
        self::assertSame('798158', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/798158', $person['url']);
        self::assertSame('SHIROMINE MIU', $person['name']);
        self::assertSame('白峰ミウ', $person['name_japanese']);
        self::assertSame(['白峰ミウ', 'SHIROMINE MIU'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame('170cm', $person['height_raw']);
        self::assertNull($person['size_raw']);
        self::assertSame(['身長' => '170cm'], $person['raw_profile']);
        self::assertSame('https://cdn.up-timely.com/image/17/actress_main/798158/O6NFn3hbh4q0D94QOzkw9XexH26UnO0vxNUWhZ2v.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertCount(2, $person['metadata']['social_links']);
        self::assertSame('shiromine_miu', $person['metadata']['social_links'][0]['external_id']);
        self::assertSame('miu_shiromine', $person['metadata']['social_links'][1]['external_id']);
        self::assertCount(12, $person['metadata']['works']);
        self::assertSame('DAZD317', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/DAZD317', $person['metadata']['works'][0]['url']);
        self::assertSame('プロの舌技でマ〇コを解きほぐすように女を舐め堕とす快感クンニ97連発BEST', $person['metadata']['works'][0]['name']);
        self::assertSame('https://cdn.up-timely.com/image/17/content/88355/jTXa8hj0Sm8TGc0yfsnsRD4dNwnusrAL1aXKPNon.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('DASS926', $person['metadata']['works'][11]['external_id']);
        self::assertSame('全17作品中 1 〜 12 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('dasdas');
        self::assertInstanceOf(DasdasCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-24' => CrawlType::Listing,
            '/works/list/genre/335' => CrawlType::Listing,
            '/works/list/label/1' => CrawlType::Listing,
            '/works/list/series/1' => CrawlType::Listing,
            '/works/detail/DAZD317' => CrawlType::Detail,
            '/actress/detail/798158' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('dasdas', $crawler->name());
    }

    public function test_shared_base_leaves_s1_output_unchanged(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), S1Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        self::assertArrayNotHasKey('label', $result->meta['movie']['metadata']);
        self::assertSame('イナバール', $result->meta['movie']['metadata']['director']);
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('dasdas/detail-' . $code . '.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function config(): \JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig
    {
        return Site::config();
    }
}
