<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Dandy;

use JOOservices\CrawlerX\Adapters\Dandy\DandyCrawler;
use JOOservices\CrawlerX\Adapters\Dandy\Types\Detail;
use JOOservices\CrawlerX\Adapters\Dandy\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class DandyTest extends TestCase
{
    private const BASE = 'https://www.choi-waru.com';

    private const UPLOADS = self::BASE . '/wordpress/wp-content/uploads/';

    public function test_dandy_detail_returns_every_labelled_field(): void
    {
        $result = (new Detail($this->clientWithFixture('dandy/detail-dandy-880.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/dandy-880/'));
        $movie = $result->meta['movie'];
        $metadata = $movie['metadata'];

        self::assertSame(self::BASE . '/dandy/dandy-880/', $result->url);
        self::assertSame('dandy-880', $movie['external_id']);
        self::assertSame('現役教師による教え子カップルたちの校内フェラチオ押収動画', $movie['title']);
        self::assertSame('DANDY-880', $movie['code']);
        self::assertStringStartsWith('とある教師が隠し持っていたのは、校内のあらゆる場所で密かに行われていた生徒カップルたちのプライベートなフェラ動画だった。', $movie['description']);
        self::assertStringEndsWith('6組6色の生々しいフェラチオ＆イチャイチャ記録。', $movie['description']);
        self::assertSame('2026-11-29', $movie['date']);
        self::assertSame(186, $movie['duration']);
        self::assertSame([], $movie['performers']);
        self::assertSame(['主観', 'パンチラ', '女子校生', 'フェラ', '美少女'], $movie['tags']);
        self::assertSame(self::UPLOADS . 'DANDY-880H1.webp', $movie['cover_url']);
        self::assertSame('DANDY', $metadata['maker']);
        self::assertSame('DANDY（ダンディ）', $metadata['label']);
        self::assertSame('dandy', $metadata['label_slug']);
        self::assertSame(self::BASE . '/dandy/', $metadata['label_url']);
        self::assertSame(self::UPLOADS . 'maker_dandy.png', $metadata['label_logo_url']);
        self::assertSame('#e95504', $metadata['label_color']);
        self::assertArrayNotHasKey('director', $metadata);
        self::assertSame('2026-11-29', $metadata['stream_date']);
        self::assertSame('2026-12-03', $metadata['dvd_release_date']);
        self::assertSame(['amount' => 3498.0, 'currency' => 'JPY', 'label' => '3,498円（税込）'], $metadata['price']);
        self::assertSame(self::UPLOADS . 'DANDY-880H1-634x900.webp', $metadata['cover_thumbnail_url']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/Sh0bDCtc3ZRyx8i8m6PUE6PROUwTJfHNMI7kvt2_Ihvbq-PVvg0JxmOQandRyr/1dandy880mhb.mp4', $metadata['sample_video_url']);
        self::assertSame('Amazon、Amazon.co.jpおよびそれらのロゴは、Amazon.com, Inc.またはその関連会社の商標または登録商標です。', $metadata['purchase_note']);
    }

    public function test_dandy_detail_genres_images_and_purchase_links(): void
    {
        $movie = (new Detail($this->clientWithFixture('dandy/detail-dandy-880.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/dandy-880/'))->meta['movie'];
        $metadata = $movie['metadata'];

        self::assertSame(
            [
                ['name' => '主観', 'url' => self::BASE . '/dandy/genre/%e4%b8%bb%e8%a6%b3/', 'slug' => '主観'],
                ['name' => 'パンチラ', 'url' => self::BASE . '/dandy/genre/%e3%83%91%e3%83%b3%e3%83%81%e3%83%a9/', 'slug' => 'パンチラ'],
                ['name' => '女子校生', 'url' => self::BASE . '/dandy/genre/%e5%a5%b3%e5%ad%90%e6%a0%a1%e7%94%9f/', 'slug' => '女子校生'],
                ['name' => 'フェラ', 'url' => self::BASE . '/dandy/genre/%e3%83%95%e3%82%a7%e3%83%a9/', 'slug' => 'フェラ'],
                ['name' => '美少女', 'url' => self::BASE . '/dandy/genre/%e7%be%8e%e5%b0%91%e5%a5%b3/', 'slug' => '美少女'],
            ],
            $metadata['genres'],
        );
        self::assertSame(
            [
                ['url' => self::UPLOADS . 'DANDY-880H1.webp', 'thumbnail_url' => self::UPLOADS . 'DANDY-880H1-634x900.webp', 'metadata' => ['width' => 845]],
                ['url' => self::UPLOADS . 'DANDY-880H4.webp', 'thumbnail_url' => self::UPLOADS . 'DANDY-880H4-660x900.webp', 'metadata' => ['width' => 880]],
            ],
            $movie['screenshots'],
        );
        self::assertSame(
            [
                ['name' => '動画を購入', 'store' => 'FANZA', 'type' => 'video', 'url' => 'https://al.dmm.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fdigital%2Fvideoa%2F-%2Fdetail%2F%3D%2Fcid%3D1dandy00880%2F&af_id=dandyDMM-001&ch=link_tool&ch_id=link'],
                ['name' => '動画を購入', 'store' => 'SOD select', 'type' => 'video', 'url' => 'https://sod-select.com/'],
                ['name' => 'DVDを購入', 'store' => 'FANZA', 'type' => 'dvd', 'url' => 'https://al.fanza.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fmono%2Fdvd%2F-%2Fdetail%2F%3D%2Fcid%3D1dandy880%2F&af_id=dandyDMM-001&ch=toolbar&ch_id=link'],
                ['name' => 'DVDを購入', 'store' => 'amazon', 'type' => 'dvd', 'url' => 'https://www.amazon.co.jp/gp/product/B0HPSJG3BK'],
            ],
            $metadata['purchase_links'],
        );
    }

    public function test_dandy_detail_keeps_every_labelled_row_and_excludes_related_works(): void
    {
        $movie = (new Detail($this->clientWithFixture('dandy/detail-dandy-880.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/dandy-880/'))->meta['movie'];

        self::assertSame(
            [
                'メーカー' => 'DANDY',
                '動画配信日' => '2026/11/29',
                'DVD発売日' => '2026/12/03',
                '時間' => '186分',
                '販売価格' => '3,498円（税込）',
                '品番' => 'DANDY-880',
                'ジャンル' => '主観 パンチラ 女子校生 フェラ 美少女',
            ],
            $movie['metadata']['source_fields'],
        );
        self::assertNotContains(self::UPLOADS . 'dandy-239_front.jpg', array_column($movie['screenshots'], 'url'));
        self::assertStringNotContainsString('dandy-239', (string) json_encode($movie));
    }

    public function test_cosmos_detail_returns_director_and_label_fields(): void
    {
        $movie = (new Detail($this->clientWithFixture('dandy/detail-hawa-389.html')))->execute(new CrawlRequestDto(url: self::BASE . '/cosmos/hawa-389/'))->meta['movie'];
        $metadata = $movie['metadata'];

        self::assertSame('hawa-389', $movie['external_id']);
        self::assertSame('ぴえん妻の肉便器修行 ゆのさん(２４歳)', $movie['title']);
        self::assertSame('HAWA-389', $movie['code']);
        self::assertStringStartsWith('とある映像を入手した。', $movie['description']);
        self::assertSame('2026-11-01', $movie['date']);
        self::assertSame(165, $movie['duration']);
        self::assertSame(['イラマチオ', '3P・4P', '潮吹き', '中出し', '人妻・主婦', 'ごっくん', '巨乳'], $movie['tags']);
        self::assertSame(self::UPLOADS . 'HAWA-389H1.webp', $movie['cover_url']);
        self::assertSame([self::UPLOADS . 'HAWA-389H1.webp', self::UPLOADS . 'HAWA-389H4.webp'], array_column($movie['screenshots'], 'url'));
        self::assertSame([self::UPLOADS . 'HAWA-389H1-634x900.webp', self::UPLOADS . 'HAWA-389H4-633x900.webp'], array_column($movie['screenshots'], 'thumbnail_url'));
        self::assertSame('コスモス映像', $metadata['maker']);
        self::assertSame('コスモス映像（コスモスエイゾウ）', $metadata['label']);
        self::assertSame('cosmos', $metadata['label_slug']);
        self::assertSame(self::UPLOADS . 'maker_cosmos.png', $metadata['label_logo_url']);
        self::assertSame('#ae0083', $metadata['label_color']);
        self::assertSame('ぴぴぴっぴ・ぴっぴ', $metadata['director']);
        self::assertSame(self::BASE . '/?s=ぴぴぴっぴ・ぴっぴ', $metadata['director_url']);
        self::assertSame('2026-11-01', $metadata['stream_date']);
        self::assertSame('2026-11-05', $metadata['dvd_release_date']);
        self::assertSame(3498.0, $metadata['price']['amount']);
        self::assertSame('ぴぴぴっぴ・ぴっぴ', $metadata['source_fields']['監督']);
        self::assertSame('HAWA-389', $metadata['source_fields']['品番']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/GnczERVcc0zdF8_MW7qQ1RQO7uLeQEvF08UkVzuqCJqs83CCoQU3pfANl4kpS/1hawa389mhb.mp4', $metadata['sample_video_url']);
        self::assertCount(4, $metadata['purchase_links']);
        self::assertCount(7, $metadata['genres']);
    }

    public function test_works_listing_returns_every_card_field_and_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('dandy/works-dandy.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/works/'));
        $first = $result->items[0];
        $movie = $first->meta['movie'];

        self::assertCount(20, $result->items);
        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertSame(self::BASE . '/dandy/dandya-055/', $first->url);
        self::assertSame('dandya-055', $movie['external_id']);
        self::assertSame('DANDYA-055', $movie['code']);
        self::assertSame('「ドスケベ過ぎてごめんなさい」 センズリが止まらないと噂のおばさんを独占468分BEST', $movie['title']);
        self::assertSame(self::UPLOADS . 'DANDYA-055H1.webp', $movie['cover_url']);
        self::assertSame('2026-12-13', $movie['date']);
        self::assertSame('dandy', $movie['metadata']['label_slug']);
        self::assertSame('2026-12-13', $movie['metadata']['stream_date']);
        self::assertSame('2026-12-17', $movie['metadata']['dvd_release_date']);
        self::assertSame(self::UPLOADS . 'DANDYA-055H1-634x900.webp', $movie['metadata']['cover_thumbnail_url']);
        self::assertSame('全ての作品', $movie['metadata']['listing_title']);
        self::assertSame(1040, $movie['metadata']['total_results']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(52, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/dandy/works/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_stream_only_cards_have_no_dvd_date_and_keep_jpg_covers(): void
    {
        $result = (new Listing($this->clientWithFixture('dandy/works-dandy.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/works/'));
        $vr = $result->items[4]->meta['movie'];

        self::assertSame('dandy8kvr-004', $vr['external_id']);
        self::assertSame('DANDY8KVR-004', $vr['code']);
        self::assertSame('2026-11-22', $vr['date']);
        self::assertArrayNotHasKey('dvd_release_date', $vr['metadata']);
        self::assertSame(self::UPLOADS . 'DANDY8KVR-004_H1_8K.jpg', $vr['cover_url']);
        self::assertSame(self::UPLOADS . 'DANDY8KVR-004_H1_8K-661x900.jpg', $vr['metadata']['cover_thumbnail_url']);
    }

    public function test_label_page_lists_new_releases_without_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('dandy/label-dandy.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/'));

        self::assertCount(10, $result->items);
        self::assertSame('新作情報', $result->items[0]->meta['movie']['metadata']['listing_title']);
        self::assertArrayNotHasKey('total_results', $result->items[0]->meta['movie']['metadata']);
        self::assertSame(['dandya-055', 'dandya-059', 'dandya-057', 'dandy-880'], array_slice(array_column(array_column(array_column($result->items, 'meta'), 'movie'), 'external_id'), 0, 4));
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_genre_page_lists_its_works_with_the_result_count(): void
    {
        $result = (new Listing($this->clientWithFixture('dandy/genre-dandy-shukan.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/genre/%e4%b8%bb%e8%a6%b3/'));

        self::assertSame(['dandy-880', 'dandya-039'], array_column(array_column(array_column($result->items, 'meta'), 'movie'), 'external_id'));
        self::assertSame('主観', $result->items[0]->meta['movie']['metadata']['listing_title']);
        self::assertSame(2, $result->items[0]->meta['movie']['metadata']['total_results']);
        self::assertSame('おはようフェラで起こしてくれるすっぴん彼女', $result->items[1]->meta['movie']['title']);
        self::assertSame(self::UPLOADS . 'DANDYA-039H1.webp', $result->items[1]->meta['movie']['cover_url']);
    }

    public function test_pages_without_works_and_non_work_urls_are_rejected(): void
    {
        $empty = '<html><body><main id="contents"><section id="top_latest"><ul class="works_list"></ul></section></main></body></html>';
        try {
            (new Listing($this->clientWithHtml($empty)))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/works/'));
            self::fail('An empty listing must fail.');
        } catch (CrawlParseException $exception) {
            self::assertStringContainsString('work cards', $exception->getMessage());
        }

        $this->expectException(CrawlParseException::class);
        (new Detail($this->clientWithFixture('dandy/detail-dandy-880.html')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/works/'));
    }

    public function test_blocked_and_unusable_detail_pages_fail(): void
    {
        try {
            (new Detail($this->clientWithHtml('<html></html>', 403)))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/dandy-880/'));
            self::fail('A 403 must be reported as blocked.');
        } catch (CrawlBlockedException) {
            self::assertTrue(true);
        }

        $this->expectException(CrawlParseException::class);
        (new Detail($this->clientWithHtml('<html><body><h1>x</h1></body></html>')))->execute(new CrawlRequestDto(url: self::BASE . '/dandy/dandy-880/'));
    }

    public function test_urls_are_inferred_without_site_or_type_override(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('dandy');

        self::assertInstanceOf(DandyCrawler::class, $crawler);
        self::assertSame('dandy', $crawler->name());
        foreach ([
            '/' => CrawlType::Listing,
            '/dandy/' => CrawlType::Listing,
            '/dandy/works/' => CrawlType::Listing,
            '/dandy/works/page/2/' => CrawlType::Listing,
            '/dandy/genre/%e4%b8%bb%e8%a6%b3/' => CrawlType::Listing,
            '/cosmos/genre/%e5%b7%a8%e4%b9%b3/page/2/' => CrawlType::Listing,
            '/dandy/dandy-880/' => CrawlType::Detail,
            '/cosmos/hawa-389/' => CrawlType::Detail,
            '/yuuyake/jera-054/' => CrawlType::Detail,
        ] as $path => $type) {
            $result = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($result->matched, $path);
            self::assertSame($type, $result->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/unknown/page-1/')->matched);
    }
}
