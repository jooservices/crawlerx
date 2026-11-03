<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\KawaiiKawaii;

use JOOservices\CrawlerX\Adapters\KawaiiKawaii\KawaiiKawaiiCrawler;
use JOOservices\CrawlerX\Adapters\KawaiiKawaii\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class KawaiiKawaiiTest extends TestCase
{
    private const BASE = 'https://kawaiikawaii.jp';

    private const CDN = 'https://cdn.up-timely.com/image/32/content/88508/';

    public function test_detail_fields_from_real_capture(): void
    {
        $result = (new Detail($this->clientWithFixture('kawaiikawaii/detail-KWBD444.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/KWBD444'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/KWBD444', $result->url);
        self::assertSame('KWBD444', $movie['external_id']);
        self::assertSame('KWBD444', $movie['code']);
        self::assertSame('伊藤舞雪8周年 エイト無限大の肉体美、最上級の無限エロス。最新12タイトル12時間傑作ベスト', $movie['title']);
        self::assertStringStartsWith('デビューから8周年。全てを包み込む圧倒的な美・伊藤舞雪。', $movie['description']);
        self::assertStringEndsWith('究極のエロがこのベストには詰まっている。', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(720, $movie['duration']);
        self::assertSame(self::CDN . '5wvpbwrvaGQs9POSakV881VyBZhJYMpqJ061w7lF.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'fKCjSZ5jT7HlN0plyIvr7f0KdN0DqGb44Xaee8QY.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('762043', $movie['performers'][0]['external_id']);
        self::assertSame('伊藤舞雪', $movie['performers'][0]['name']);
        self::assertSame('伊藤舞雪', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/762043', $movie['performers'][0]['url']);
        self::assertSame(['美少女', '中出し', '単体作品', 'アクメ・オーガズム', '女優ベスト・総集編'], $movie['tags']);

        $meta = $movie['metadata'];
        self::assertSame('kawaii*', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertCount(5, $meta['genres']);
        self::assertSame(['name' => '美少女', 'url' => self::BASE . '/works/list/genre/330', 'external_id' => '330'], $meta['genres'][0]);
        self::assertSame('9155', $meta['genres'][3]['external_id']);
        self::assertSame([['name' => 'kawaii*ベスト', 'url' => self::BASE . '/works/list/series/4173', 'external_id' => '4173']], $meta['series']);
        self::assertSame([['name' => 'kawaii*', 'url' => self::BASE . '/works/list/label/6707', 'external_id' => '6707']], $meta['label']);
        self::assertSame([['name' => '2026年12月1日', 'url' => self::BASE . '/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $meta['release_links']);
        self::assertSame([
            ['format' => 'DVD', 'code' => 'KWBD444', 'duration' => 720, 'price' => '4,200円（税込）'],
            ['format' => 'Blu-ray', 'code' => '9KWBD444', 'duration' => 720, 'price' => '5,300円（税込）'],
        ], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/KDUaQxIsQrS6AHqNd7QSMBNK_zmggEwkOU2cJG88OXWl6MfB31UeFb75ObHGQ8/kwbd00444dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . 'sample_video_image_file/qapwoC3Bg6ChdV5GdpbSyAswCZYJXwTYISj4LOzZ.jpg', $meta['sample_video_poster_url']);
        self::assertCount(3, $meta['purchase_links']);
        self::assertSame('動画を購入 ダウンロード+ストリーミング', $meta['purchase_links'][0]['name']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=kwbd00444/WILLaffi-022', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 4,200円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('Blu-rayを購入 定価 5,300円（税込）', $meta['purchase_links'][2]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('品番 DVDKWBD444 Blu-ray9KWBD444', $meta['source_fields']['品番']);
        self::assertSame('価格 DVD4,200円（税込） Blu-ray5,300円（税込）', $meta['source_fields']['価格']);
        self::assertSame('監督 ---', $meta['source_fields']['監督']);
        self::assertStringNotContainsString('CAWB056', (string) json_encode($movie));
    }

    public function test_date_index_walks_first_release_group(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('kawaiikawaii/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('kawaiikawaii/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(12, $result->items);
        self::assertSame(self::BASE . '/works/detail/CAWB056', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame(12, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-12-01', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $movie['metadata']['release_group']['url']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_date_listing_card_fields(): void
    {
        $url = self::BASE . '/works/list/date/2026-12-01';
        $result = (new Listing($this->clientWithFixture('kawaiikawaii/list-2026-12-01.html'), Site::config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(12, $result->items);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('CAWB056', $movie['external_id']);
        self::assertSame('CAWB056', $movie['code']);
        self::assertSame('【kawaii*20周年 特別企画 】ニッポンのAV美少女スーパースター30人 ここに集う。史上最大ハーレム大共演2026', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/32/content/88378/T0exqvL8GbXc4nZkbP8VYwrnAlJXud0M9QGsXq3Q.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame(28, $movie['metadata']['performer_count']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertSame('全12作品中 1 〜 12 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('kawaii*', $movie['metadata']['maker']);
        $titled = array_values(array_filter($result->items, static fn($item): bool => $item->meta['movie']['external_id'] === 'KWBD444'));
        self::assertSame('762043', $titled[0]->meta['movie']['performers'][0]['external_id']);
        self::assertSame('伊藤舞雪', $titled[0]->meta['movie']['performers'][0]['name']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_second_date_listing_has_cards(): void
    {
        $result = (new Listing($this->clientWithFixture('kawaiikawaii/list-2026-11-03.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-11-03'));
        self::assertGreaterThan(0, count($result->items));
        self::assertSame('2026-11-03', $result->items[0]->meta['movie']['date']);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('kawaiikawaii/actress-876969.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/876969'));
        $person = $result->meta['performer'];
        $image = 'https://cdn.up-timely.com/image/32/actress_main/876969/REg3iC0QyfJ6n7YJB4piAU42Rmoqu3gwVXKRsums.jpg';
        self::assertSame('876969', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/876969', $person['url']);
        self::assertSame('NANANO NANO', $person['name']);
        self::assertSame('菜々野なの', $person['name_japanese']);
        self::assertSame(['菜々野なの', 'NANANO NANO'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertSame('B--cm (F) W54cm H--cm', $person['size_raw']);
        self::assertSame(['3サイズ' => 'B--cm (F) W54cm H--cm'], $person['raw_profile']);
        self::assertSame($image, $person['profile_image_url']);
        self::assertSame([$image], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('CAWB070', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/CAWB070', $person['metadata']['works'][0]['url']);
        self::assertSame('ショートカットの最強エース! くびれスレンダー54cm美乳Fカップのスマイル女子はめちゃめちゃエロすぎた! 菜々野なの AVデビュー', $person['metadata']['works'][0]['name']);
        self::assertSame('https://cdn.up-timely.com/image/32/content/88507/5yjfrVWeXOcC9Yr6Ft6mifIaZACGhVAy44Z7seaW.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('kawaiikawaii');
        self::assertInstanceOf(KawaiiKawaiiCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-12-01' => CrawlType::Listing,
            '/works/list/genre/330' => CrawlType::Listing,
            '/works/list/label/6707' => CrawlType::Listing,
            '/works/list/series/4173' => CrawlType::Listing,
            '/works/detail/KWBD444' => CrawlType::Detail,
            '/actress/detail/876969' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('kawaiikawaii', $crawler->name());
    }
}
