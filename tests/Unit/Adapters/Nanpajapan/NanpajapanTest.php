<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Nanpajapan;

use JOOservices\CrawlerX\Adapters\Nanpajapan\NanpajapanCrawler;
use JOOservices\CrawlerX\Adapters\Nanpajapan\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class NanpajapanTest extends TestCase
{
    private const BASE = 'https://nanpa-japan.jp';

    private const CDN = 'https://cdn.up-timely.com/image/27/content/';

    public function test_detail_exposes_every_labelled_row_without_series_or_director(): void
    {
        $result = $this->detail('NPJS299');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/NPJS299', $result->url);
        self::assertSame('NPJS299', $movie['external_id']);
        self::assertSame('NPJS299', $movie['code']);
        self::assertSame('【闇バイト】1日100万 仕事内容:高級住宅街の一軒家で1日滞在。性奴〇ーせいどれいーになるただそれだけ。', $movie['title']);
        self::assertStringStartsWith('【闇バイト】SNSやインターネット掲示板などで', $movie['description']);
        self::assertStringEndsWith('＃肉便器＃奴隷＃潮マンコ＃水責め', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame(self::CDN . '88467/MNWsnv5JOSFUIUM80q7JyEWehbo8DOBaOHYGlV5F.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '88467/ZJQ0H1S5wh134JM7nsLZH1sLZTDxs6gvsjqYcufl.jpg', $movie['screenshots'][10]['url']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['ミニ系・小柄', '羞恥', '顔射', '中出し', '制服'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('NANPA JAPAN', $meta['maker']);
        self::assertNull($meta['director']);
        self::assertSame([], $meta['series']);
        self::assertArrayNotHasKey('label', $meta);
        self::assertSame([['name' => '2026年12月1日', 'url' => self::BASE . '/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $meta['release_links']);
        self::assertSame(['5019', '333', '329', '128', '9064'], array_column($meta['genres'], 'external_id'));
        self::assertSame(['ミニ系・小柄', '羞恥', '顔射', '中出し', '制服'], array_column($meta['genres'], 'name'));
        self::assertSame(self::BASE . '/works/list/genre/5019', $meta['genres'][0]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'NPJS299', 'duration' => 120, 'price' => '4,200円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/PwZ-TAsTFA79XwMWStG_ZGrzIfE289AMqPyr3oWWwP-5ZfwIAUXlkgoUGI2hoU/npjs00299dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::CDN . '88467/sample_video_image_file/28EbudLpOKa3UbpOGZOqdNrIMLLsjRTOunnVYbfn.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=npjs00299/WILLaffi-050', $meta['purchase_links'][0]['url']);
        self::assertSame('動画を購入 ダウンロード+ストリーミング', $meta['purchase_links'][0]['name']);
        self::assertSame('https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=npjs299/WILLaffi-050', $meta['purchase_links'][1]['url']);
        self::assertSame('DVDを購入 定価 4,200円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['発売日', 'シリーズ', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('発売日 2026年12月1日', $meta['source_fields']['発売日']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('ジャンル ミニ系・小柄 羞恥 顔射 中出し 制服', $meta['source_fields']['ジャンル']);
        self::assertSame('監督', $meta['source_fields']['監督']);
        self::assertSame('品番 DVDNPJS299', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD120分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD4,200円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('NPJS293', (string) json_encode($movie));
    }

    public function test_series_best_detail_and_placeholder_director(): void
    {
        $movie = $this->detail('NPJB134')->meta['movie'];
        self::assertSame('NPJB134', $movie['code']);
        self::assertSame('ご奉仕じゅぽじゅぽ搾精フェラチオ230分BEST', $movie['title']);
        self::assertStringStartsWith('最もシコりやすい射精直前シーンをたっぷり収録！', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(230, $movie['duration']);
        self::assertSame(self::CDN . '88466/0fh7kCXAOQPEoPk1bAgYL1NkiRIg9o7xUJ4MC1BF.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame(['フェラ', '美少女', '素人', 'アクメ・オーガズム', 'ベスト・総集編'], $movie['tags']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame([['name' => 'NPJベスト', 'url' => self::BASE . '/works/list/series/5734', 'external_id' => '5734']], $movie['metadata']['series']);
        self::assertSame([['format' => 'DVD', 'code' => 'NPJB134', 'duration' => 230, 'price' => '4,200円（税込）']], $movie['metadata']['formats']);
        self::assertStringEndsWith('/npjb00134dm.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame('シリーズ NPJベスト', $movie['metadata']['source_fields']['シリーズ']);
        self::assertSame('監督 ---', $movie['metadata']['source_fields']['監督']);
        self::assertCount(7, $movie['metadata']['source_fields']);
    }

    public function test_date_listing_cards(): void
    {
        $result = (new Listing($this->clientWithFixture('nanpajapan/list-2026-12-01.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/date/2026-12-01'));
        self::assertCount(6, $result->items);
        self::assertSame(self::BASE . '/works/detail/NPJS299', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('NPJS299', $movie['external_id']);
        self::assertSame('NPJS299', $movie['code']);
        self::assertSame('【闇バイト】1日100万 仕事内容:高級住宅街の一軒家で1日滞在。性奴〇ーせいどれいーになるただそれだけ。', $movie['title']);
        self::assertSame(self::CDN . '88467/YXfGFNB0vwUeJDmxHtMJDV0DspBVGK2CVzGtGwPC.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('NANPA JAPAN', $movie['metadata']['maker']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertSame('全6作品中 1 〜 6 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('NPJB134', $result->items[5]->meta['movie']['code']);
        self::assertSame(self::CDN . '88464/pgqU2jpLBM4x7Z26P5Xhxs1VgWfmDD4SGfcBEO7q.jpg', $result->items[4]->meta['movie']['cover_url']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_genre_listing_strips_tracking_query(): void
    {
        $result = (new Listing($this->clientWithFixture('nanpajapan/list-genre-333.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/genre/333'));
        self::assertCount(9, $result->items);
        self::assertSame(self::BASE . '/works/detail/NPJS299', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('NNPJ251', $movie['external_id']);
        self::assertSame('某税理士事務所に勤める隠れドM美人インテリ税理士みきさん(仮名)25才がAVデビューでマゾ覚醒!!', $movie['title']);
        self::assertSame(self::CDN . '41939/NNPJ251_1.jpg', $movie['cover_url']);
        self::assertNull($movie['date']);
        self::assertSame('羞恥', $movie['metadata']['search_key']);
        self::assertSame('全9作品中 1 〜 9 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_date_index_follows_first_release_group_and_paginates(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('nanpajapan/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('nanpajapan/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(6, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026年12月1日 作品数：6', $group['name']);
        self::assertSame('2026-12-01', $group['date']);
        self::assertSame(6, $group['count']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $group['url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('nanpajapan');
        self::assertInstanceOf(NanpajapanCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-12-01' => CrawlType::Listing,
            '/works/list/genre/333' => CrawlType::Listing,
            '/works/list/series/5734' => CrawlType::Listing,
            '/works/detail/NPJS299' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('nanpajapan', $crawler->name());
    }

    private function detail(string $code): \JOOservices\CrawlerX\Dto\CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('nanpajapan/detail-' . $code . '.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }
}
