<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Rocket;

use InvalidArgumentException;
use JOOservices\CrawlerX\Adapters\Rocket\RocketCrawler;
use JOOservices\CrawlerX\Adapters\Rocket\Types\Detail;
use JOOservices\CrawlerX\Adapters\Rocket\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class RocketTest extends TestCase
{
    private const BASE = 'https://rocket-inc.net';

    private const IMAGES = 'https://i0.wp.com/rocket-inc.net/wp-content/uploads/';

    public function test_detail_fields_from_real_capture_exclude_navigation(): void
    {
        $result = (new Detail($this->clientWithFixture('rocket/detail-rctd-774.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/rctd-774/'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/rctd-774/', $result->url);
        self::assertSame('movie', $result->entityType);
        self::assertSame('rctd-774', $movie['external_id']);
        self::assertSame('妄想アイテム究極進化シリーズ　淫魔痴女化サキュバス光線銃　～口調もエロスキルも好みのサキュバス設定ができる夢の洗脳光線銃～', $movie['title']);
        self::assertSame('RCTD-774', $movie['code']);
        self::assertSame('身近な女をサキュバスに変身させてヤられたい！彼女とSEXレスに悩む主人公が謎の紳士から禍々しい光線銃を貰う。上から目線のドS、優しくてラブラブ…自分好みのサキュバスを召喚！ベロチュー、淫語フェラ、スパイダー騎乗位で搾精SEX！美しい淫魔との激しいSEXを夢見ていたはずが…結末は本編のお楽しみ。', $movie['description']);
        self::assertSame('2026-12-03', $movie['date']);
        self::assertSame(130, $movie['duration']);
        self::assertSame(self::IMAGES . '2026/11/rctd-774-jacket.jpg?fit=800%2C538&ssl=1', $movie['cover_url']);
        self::assertSame(['有村のぞみ', '虹村ゆみ'], array_column($movie['performers'], 'name'));
        self::assertSame(['有村のぞみ', '虹村ゆみ'], array_column($movie['performers'], 'name_japanese'));
        self::assertSame(['有村のぞみ', '虹村ゆみ'], array_column($movie['performers'], 'external_id'));
        self::assertSame([self::BASE . '/works_actress/%e6%9c%89%e6%9d%91%e3%81%ae%e3%81%9e%e3%81%bf/', self::BASE . '/works_actress/%e8%99%b9%e6%9d%91%e3%82%86%e3%81%bf/'], array_column($movie['performers'], 'url'));
        self::assertSame(['企画', '妄想'], $movie['tags']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame(self::IMAGES . '2026/11/rctd-774-sample-1.jpg?fit=800%2C535&ssl=1', $movie['screenshots'][0]['url']);
        self::assertSame(self::IMAGES . '2026/11/rctd-774-sample-1.jpg?resize=480%2C360&ssl=1', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(self::IMAGES . '2026/11/rctd-774-sample-10.jpg?fit=800%2C535&ssl=1', $movie['screenshots'][9]['url']);
        $meta = $movie['metadata'];
        self::assertSame('ROCKET', $meta['maker']);
        self::assertSame('セントラル山本', $meta['director']);
        self::assertArrayNotHasKey('series', $meta);
        self::assertArrayNotHasKey('series_links', $meta);
        self::assertArrayNotHasKey('label', $meta);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=1rctd774/size=1280_720/', $meta['sample_video_url']);
        self::assertSame('2026年12月03日', $meta['release_date_raw']);
        self::assertSame('2026-11-05', $meta['stream_release_date']);
        self::assertSame('2026-11-05', $meta['posted_date']);
        self::assertSame('2026-11-05', $meta['modified_date']);
        self::assertSame([
            ['name' => '企画', 'url' => self::BASE . '/works_genre/%e4%bc%81%e7%94%bb/', 'external_id' => '企画'],
            ['name' => '妄想', 'url' => self::BASE . '/works_genre/%e5%a6%84%e6%83%b3/', 'external_id' => '妄想'],
        ], $meta['genres']);
        self::assertSame([['name' => 'セントラル山本', 'url' => self::BASE . '/works_director/%e3%82%bb%e3%83%b3%e3%83%88%e3%83%a9%e3%83%ab%e5%b1%b1%e6%9c%ac/', 'external_id' => 'セントラル山本']], $meta['director_links']);
        self::assertSame([
            ['group' => 'オンライン視聴', 'name' => 'FANZA', 'url' => 'https://al.fanza.co.jp/?lurl=https%3A%2F%2Fvideo.dmm.co.jp%2Fav%2Fcontent%2F%3Fid%3D1rctd00774&af_id=rocket-inc-001&ch=toolbar&ch_id=link'],
            ['group' => 'パッケージ版購入', 'name' => 'FANZA', 'url' => 'https://al.fanza.co.jp/?lurl=https%3A%2F%2Fwww.dmm.co.jp%2Fmono%2Fdvd%2F-%2Fdetail%2F%3D%2Fcid%3D1rctd774%2F&af_id=rocket-inc-001&ch=toolbar&ch_id=link'],
            ['group' => 'パッケージ版購入', 'name' => 'Amazon', 'url' => 'https://www.amazon.co.jp/dp/B0HPSC2ZRL/ref=nosim?tag=rocket9115-22'],
        ], $meta['purchase_links']);
        self::assertSame(29770, $meta['post_id']);
        self::assertSame(['actress' => [27, 1852], 'genre' => [24, 93], 'director' => [23]], $meta['term_ids']);
        self::assertSame([
            '女優名' => '有村のぞみ, 虹村ゆみ',
            '監督名' => 'セントラル山本',
            'ジャンル名' => '企画, 妄想',
            '品番' => 'RCTD-774',
            '収録時間' => '130分',
            'DVD発売日' => '2026年12月03日',
            '先行配信日' => '2026年11月05日',
        ], $meta['source_fields']);
        self::assertStringNotContainsString('作品一覧', json_encode($movie, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function test_detail_with_several_cast_members_and_a_series(): void
    {
        $result = (new Detail($this->clientWithFixture('rocket/detail-rctd-683.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/rctd-683'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/rctd-683/', $result->url);
        self::assertSame('ROCKET17周年記念ユーザーリクエスト祭り　無様エロ再び！裸より恥ずかしい爆発黒焦げ女', $movie['title']);
        self::assertSame('RCTD-683', $movie['code']);
        self::assertSame('2025-09-11', $movie['date']);
        self::assertSame(100, $movie['duration']);
        self::assertSame(['若月もあ', '虹村ゆみ', '雨宮凛'], array_column($movie['performers'], 'name'));
        self::assertSame(['フェチ', 'リクエスト', '企画', '羞恥'], $movie['tags']);
        self::assertCount(10, $movie['screenshots']);
        $meta = $movie['metadata'];
        self::assertSame('神戸たろう', $meta['director']);
        self::assertSame('無様エロ再び！裸より恥ずかしい爆発黒焦げ女', $meta['series']);
        self::assertSame([[
            'name' => '無様エロ再び！裸より恥ずかしい爆発黒焦げ女',
            'url' => self::BASE . '/works_series/%e7%84%a1%e6%a7%98%e3%82%a8%e3%83%ad%e5%86%8d%e3%81%b3%ef%bc%81%e8%a3%b8%e3%82%88%e3%82%8a%e6%81%a5%e3%81%9a%e3%81%8b%e3%81%97%e3%81%84%e7%88%86%e7%99%ba%e9%bb%92%e7%84%a6%e3%81%92%e5%a5%b3/',
            'external_id' => '無様エロ再び！裸より恥ずかしい爆発黒焦げ女',
        ]], $meta['series_links']);
        self::assertSame('2025-08-11', $meta['stream_release_date']);
        self::assertSame('2025-08-11', $meta['posted_date']);
        self::assertSame('2025-12-10', $meta['modified_date']);
        self::assertSame(22625, $meta['post_id']);
        self::assertSame([1853, 1852, 1851], $meta['term_ids']['actress']);
        self::assertSame([1887], $meta['term_ids']['series']);
        self::assertSame('無様エロ再び！裸より恥ずかしい爆発黒焦げ女', $meta['source_fields']['シリーズ名']);
        self::assertSame('若月もあ, 虹村ゆみ, 雨宮凛', $meta['source_fields']['女優名']);
        self::assertCount(3, $meta['purchase_links']);
    }

    public function test_listing_reads_every_card_and_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('rocket/works.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/'));
        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(24, $result->items);
        $first = $result->items[0];
        self::assertSame(self::BASE . '/works/rctd-770/', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('rctd-770', $movie['external_id']);
        self::assertSame('最もエロくて強いレズは誰だ！？　ガチンコ全裸レズバトルリターンズ9　真っ正面からノーガードのイカセ合い4人制トーナメント戦', $movie['title']);
        self::assertSame(self::IMAGES . '2026/11/rctd-770-jacket.jpg?fit=800%2C538&ssl=1', $movie['cover_url']);
        self::assertSame('2026-12-03', $movie['date']);
        self::assertSame('ROCKET', $movie['metadata']['maker']);
        self::assertSame(['name' => '2026年12月3日 発売作品', 'date' => '2026-12-03'], $movie['metadata']['release_group']);
        self::assertArrayNotHasKey('taxonomy', $movie['metadata']);
        $second = $result->items[6]->meta['movie'];
        self::assertSame('rctd-764', $second['external_id']);
        self::assertSame('2026-11-05', $second['date']);
        $last = $result->items[23]->meta['movie'];
        self::assertSame('rctd-757', $last['external_id']);
        self::assertSame('ROCKET18周年記念ユーザーリクエスト祭り　乗っ取りデンタルクリニック', $last['title']);
        self::assertSame('2026-09-03', $last['date']);
        self::assertSame(1, $result->pagination?->currentPage);
        self::assertSame(66, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_taxonomy_archive_lists_the_term_works(): void
    {
        $url = self::BASE . '/works_actress/%e8%99%b9%e6%9d%91%e3%82%86%e3%81%bf/';
        $result = (new Listing($this->clientWithFixture('rocket/works-actress-nijimura-yumi.html')))->execute(new CrawlRequestDto(url: $url));
        self::assertSame(['rctd-774', 'rctd-683'], array_map(static fn($item): string => $item->meta['movie']['external_id'], $result->items));
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('ROCKET17周年記念ユーザーリクエスト祭り　無様エロ再び！裸より恥ずかしい爆発黒焦げ女', $movie['title']);
        self::assertNull($movie['date']);
        self::assertSame(self::IMAGES . '2025/08/rctd-683-jacket.jpg?fit=800%2C538&ssl=1', $movie['cover_url']);
        self::assertSame(['type' => 'actress', 'slug' => '虹村ゆみ', 'heading' => '虹村ゆみの作品', 'name' => '虹村ゆみ'], $movie['metadata']['taxonomy']);
        self::assertArrayNotHasKey('release_group', $movie['metadata']);
        self::assertSame(1, $result->pagination?->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_pages_without_expected_markup_are_rejected(): void
    {
        $empty = $this->clientWithHtml('<html><body><main><p>Not found</p></main></body></html>');
        try {
            (new Detail($empty))->execute(new CrawlRequestDto(url: self::BASE . '/works/rctd-1/'));
            self::fail('A page without a work article must be rejected.');
        } catch (CrawlParseException $exception) {
            self::assertStringContainsString('work article', $exception->getMessage());
        }

        $this->expectException(CrawlParseException::class);
        (new Listing($empty))->execute(new CrawlRequestDto(url: self::BASE . '/works/'));
    }

    public function test_detail_rejects_a_url_that_is_not_a_work(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Detail($this->clientWithFixture('rocket/detail-rctd-774.html')))->execute(new CrawlRequestDto(url: self::BASE . '/works/page/2/'));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('rocket');
        self::assertInstanceOf(RocketCrawler::class, $crawler);
        $expected = [
            '/works/' => CrawlType::Listing,
            '/works/page/2/' => CrawlType::Listing,
            '/works_actress/%e8%99%b9%e6%9d%91%e3%82%86%e3%81%bf/' => CrawlType::Listing,
            '/works_genre/%e4%bc%81%e7%94%bb/page/2/' => CrawlType::Listing,
            '/works_director/x' => CrawlType::Listing,
            '/works_series/x/' => CrawlType::Listing,
            '/works/rctd-774/' => CrawlType::Detail,
            '/works/rctd-683' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/news/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/works_label/x/')->matched);
        self::assertSame('rocket', $crawler->name());
    }
}
