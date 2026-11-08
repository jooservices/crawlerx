<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mvg;

use JOOservices\CrawlerX\Adapters\Ideapocket\Site as IdeapocketSite;
use JOOservices\CrawlerX\Adapters\Mvg\MvgCrawler;
use JOOservices\CrawlerX\Adapters\Mvg\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class MvgTest extends TestCase
{
    private const BASE = 'https://mvg.jp';

    public function test_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('MVSD710');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/MVSD710', $result->url);
        self::assertSame('MVSD710', $movie['external_id']);
        self::assertSame('MVSD710', $movie['code']);
        self::assertSame('人妻自宅サロン 底辺クズ隣人の汚らわしいデカマラに堕ちた若妻エステティシャン', $movie['title']);
        self::assertStringStartsWith('こんなに濡らしやがって…', $movie['description']);
        self::assertStringEndsWith('ぶち込んで下さいって言えよ…。', $movie['description']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/88213/NM0YVfYfSYVre2DX77RVDmAptxu6JvNb8jtzzjQ0.jpg', $movie['cover_url']);
        self::assertCount(20, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/88213/5aWTDwPgB8ZSJnyRROotM2Ok9taGCdkghoA4qiaa.jpg', $movie['screenshots'][11]['url']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/88213/zNg5zNv5D9fEAa4u8fc71agnUIz82YXDkzPgSvGE.jpg', $movie['screenshots'][19]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('彩月七緒', $movie['performers'][0]['name']);
        self::assertSame('彩月七緒', $movie['performers'][0]['name_japanese']);
        self::assertNull($movie['performers'][0]['external_id']);
        self::assertNull($movie['performers'][0]['url']);
        self::assertSame(['寝取り・寝取られ・ＮＴＲ', '巨乳', 'マッサージ・リフレ', '中出し', '人妻・主婦'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('MVG', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertCount(5, $meta['genres']);
        self::assertSame(['name' => '寝取り・寝取られ・ＮＴＲ', 'url' => self::BASE . '/works/list/genre/138', 'external_id' => '138'], $meta['genres'][0]);
        self::assertSame('205', $meta['genres'][4]['external_id']);
        self::assertSame([['name' => '2026年11月17日', 'url' => self::BASE . '/works/list/date/2026-11-17', 'external_id' => '2026-11-17']], $meta['release_links']);
        self::assertSame([['format' => 'DVD', 'code' => 'MVSD710', 'duration' => 120, 'price' => '4,598円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/Si94DwYX3h1zUsJBDG6ZCthXqFGFtaWmJ9GhtVXc6yZl-G7ycdnMJkj9JZfZHB/mvsd00710dm.mp4', $meta['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/88213/sample_video_image_file/QmW6Tgr5WOQ7BsgPs2IAmaaSdvsnXExXmJdQTS5D.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=mvsd00710/WILLaffi-053', $meta['purchase_links'][0]['url']);
        self::assertSame('動画を購入 ダウンロード+ストリーミング', $meta['purchase_links'][0]['name']);
        self::assertSame('DVDを購入 定価 4,598円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('女優 彩月七緒', $meta['source_fields']['女優']);
        self::assertSame('価格 DVD4,598円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('MVSD696', (string) json_encode($movie));
    }

    public function test_series_best_detail_with_many_performers_and_placeholder_director(): void
    {
        $movie = $this->detail('MVBD200')->meta['movie'];
        self::assertSame('MVBD200', $movie['code']);
        self::assertSame('拘束！開脚！マングリ！晒しものになったケツ穴と盛りのついた淫乱マ◯コを完膚無きまで犯して嬲る2穴イカセ拷問BEST8時間', $movie['title']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertSame(480, $movie['duration']);
        self::assertCount(13, $movie['screenshots']);
        self::assertCount(21, $movie['performers']);
        self::assertSame('美波こづえ', $movie['performers'][0]['name']);
        self::assertSame('吹石れな', $movie['performers'][20]['name']);
        self::assertSame(['アナル', '拘束', '拷問', '3P、4P', 'ベスト・総集編'], $movie['tags']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame([['name' => '編集作品（エムズビデオグループ）', 'url' => self::BASE . '/works/list/series/5804', 'external_id' => '5804']], $movie['metadata']['series']);
        self::assertSame([['format' => 'DVD', 'code' => 'MVBD200', 'duration' => 480, 'price' => '3,498円（税込）']], $movie['metadata']['formats']);
        self::assertSame('監督 ---', $movie['metadata']['source_fields']['監督']);
        self::assertSame('シリーズ 編集作品（エムズビデオグループ）', $movie['metadata']['source_fields']['シリーズ']);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('mvg/dates.html')],
            self::BASE . '/works/list/date/2026-11-17' => ['body' => $this->loadFixture('mvg/list-2026-11-17.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(4, $result->items);
        self::assertSame(self::BASE . '/works/detail/MVSD710', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('MVSD710', $movie['external_id']);
        self::assertSame('MVSD710', $movie['code']);
        self::assertSame('人妻自宅サロン', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/88213/4SK7vrq2g6KyRPmZj8edNz8ebqqXzEso5usmHk1S.jpg', $movie['cover_url']);
        self::assertSame('2026-11-17', $movie['date']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('彩月七緒', $movie['performers'][0]['name']);
        self::assertSame('MVG', $movie['metadata']['maker']);
        self::assertSame(4, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-17', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-17', $movie['metadata']['release_group']['url']);
        self::assertSame('全4作品中 1 〜 4 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月17日', $movie['metadata']['search_key']);
        self::assertSame(['MVSD710', 'MVSD709', 'MVSH004', 'MVBD200'], array_map(static fn(CrawlItemResultDto $item): string => $item->meta['movie']['external_id'], $result->items));
        self::assertSame(83, $result->pagination->lastPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates(): void
    {
        $url = self::BASE . '/works/list/genre/215';
        $result = (new Listing($this->clientWithFixture('mvg/list-genre-215.html'), Site::config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/MVSD710', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('MVSH007', $movie['external_id']);
        self::assertSame('喉犯 喉姦 美顔どろどろ狂気の精飲イラマチオ', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/18/content/87728/mGsFYRa3Zqbe7TyaJ6ODFJzp1mlm2iAG9grem7dJ.jpg', $movie['cover_url']);
        self::assertSame('葉月なぎさ', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('巨乳', $movie['metadata']['search_key']);
        self::assertSame('全163作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('mvg');
        self::assertInstanceOf(MvgCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-17' => CrawlType::Listing,
            '/works/list/genre/215' => CrawlType::Listing,
            '/works/list/series/5804' => CrawlType::Listing,
            '/works/detail/MVSD710' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('mvg', $crawler->name());
    }

    public function test_sites_with_actress_pages_keep_linked_performers(): void
    {
        $result = (new Detail($this->clientWithFixture('ideapocket/detail-IDPC034.html'), IdeapocketSite::config()))->execute(new CrawlRequestDto(url: 'https://ideapocket.com/works/detail/IDPC034'));
        self::assertSame('876796', $result->meta['movie']['performers'][0]['external_id']);
        self::assertFalse((new SiteConfig(baseUrl: 'https://example.test', maker: 'X'))->plainPerformers);
    }

    private function detail(string $code): CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('mvg/detail-' . $code . '.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }
}
