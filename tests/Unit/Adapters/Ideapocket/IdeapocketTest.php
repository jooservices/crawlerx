<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Ideapocket;

use JOOservices\CrawlerX\Adapters\Ideapocket\IdeapocketCrawler;
use JOOservices\CrawlerX\Adapters\Ideapocket\Site;
use JOOservices\CrawlerX\Adapters\S1\Site as S1Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class IdeapocketTest extends TestCase
{
    private const BASE = 'https://ideapocket.com';

    public function test_debut_detail_fields_from_real_capture(): void
    {
        $result = $this->detail('IDPC034');
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/IDPC034', $result->url);
        self::assertSame('IDPC034', $movie['external_id']);
        self::assertSame('IDPC034', $movie['code']);
        self::assertSame('FIRST IMPRESSION198 美咲柚葉 30歳 都内病院で働く6年目の現役看護師', $movie['title']);
        self::assertStringStartsWith('自己紹介してもいいでしょうか？美咲 柚葉', $movie['description']);
        self::assertStringEndsWith('そろそろ優しいHcupお姉さんが恋しいんじゃない？', $movie['description']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame(170, $movie['duration']);
        self::assertSame('https://cdn.up-timely.com/image/4/content/88413/H9YLXG0tUoxFndCpGp4PdoxcCIkgwrj5W9OCKrjN.jpg', $movie['cover_url']);
        self::assertCount(11, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame('https://cdn.up-timely.com/image/4/content/88413/1hfShSbba53FGeRwy4snPtgRFbVXGxH53Ka2b9S9.jpg', $movie['screenshots'][10]['url']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('876796', $movie['performers'][0]['external_id']);
        self::assertSame('美咲柚葉', $movie['performers'][0]['name']);
        self::assertSame('美咲柚葉', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/876796', $movie['performers'][0]['url']);
        self::assertSame(['看護婦・ナース', '顔射', '巨乳', 'ドキュメンタリー', 'デビュー作品'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('IDEAPOCKET', $meta['maker']);
        self::assertSame('嵐山みちる', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => 'ティッシュ', 'url' => self::BASE . '/works/list/label/2771', 'external_id' => '2771']], $meta['label']);
        self::assertSame([['name' => '2026年12月1日', 'url' => self::BASE . '/works/list/date/2026-12-01', 'external_id' => '2026-12-01']], $meta['release_links']);
        self::assertSame('9068', $meta['genres'][0]['external_id']);
        self::assertSame(self::BASE . '/works/list/genre/315', $meta['genres'][4]['url']);
        self::assertCount(5, $meta['genres']);
        self::assertSame([['format' => 'DVD', 'code' => 'IDPC034', 'duration' => 170, 'price' => '4,700円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/NgobRT81OMR0lQ4UXsfRB4uUw5uWC5oBAOp-JwTtbNutuCe6T1153jJnROfxRu/idpc00034dm.mp4', $meta['sample_video_url']);
        self::assertSame('https://cdn.up-timely.com/image/4/content/88413/sample_video_image_file/nBWPLIV9ZL7sJfqVzCpjQM2neyVyHy1yYD2TRP5h.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=idpc00034/WILLaffi-008', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 4,700円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame('レーベル ティッシュ', $meta['source_fields']['レーベル']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertCount(9, $meta['source_fields']);
        self::assertStringNotContainsString('IDPC032', (string) json_encode($movie));
    }

    public function test_series_best_detail_and_placeholder_director(): void
    {
        $movie = $this->detail('IPOK042')->meta['movie'];
        self::assertSame('IPOK042', $movie['code']);
        self::assertSame(720, $movie['duration']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame('858379', $movie['performers'][0]['external_id']);
        self::assertSame('三澄寧々', $movie['performers'][0]['name']);
        self::assertSame(['お姉さん', '巨乳', '痴女', 'パイズリ', '女優ベスト・総集編'], $movie['tags']);
        self::assertNull($movie['metadata']['director']);
        self::assertSame('4525', $movie['metadata']['series'][0]['external_id']);
        self::assertSame('アイデアポケットBEST', $movie['metadata']['series'][0]['name']);
        self::assertSame('2769', $movie['metadata']['label'][0]['external_id']);
        self::assertSame('アイデアポケットBEST', $movie['metadata']['label'][0]['name']);
        self::assertSame('4,700円（税込）', $movie['metadata']['formats'][0]['price']);
        self::assertSame('---', explode(' ', $movie['metadata']['source_fields']['監督'])[1]);
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('ideapocket/dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('ideapocket/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(12, $result->items);
        self::assertSame(self::BASE . '/works/detail/IDPC034', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('IDPC034', $movie['external_id']);
        self::assertSame('IDPC034', $movie['code']);
        self::assertSame('FIRST IMPRESSION198 美咲柚葉 30歳 都内病院で働く6年目の現役看護師', $movie['title']);
        self::assertSame('https://cdn.up-timely.com/image/4/content/88413/JcF4zBz5aiBm8M3oE7nQ1g1n2wxjMUXf5UL8lcv3.jpg', $movie['cover_url']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertSame('876796', $movie['performers'][0]['external_id']);
        self::assertSame('美咲柚葉', $movie['performers'][0]['name']);
        self::assertSame(12, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-12-01', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $movie['metadata']['release_group']['url']);
        self::assertSame('全12作品中 1 〜 12 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年12月1日', $movie['metadata']['search_key']);
        self::assertSame('IPOK042', $result->items[11]->meta['movie']['external_id']);
        self::assertSame('三澄寧々', $result->items[11]->meta['movie']['performers'][0]['name']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_genre_listing_paginates_and_strips_tracking_query(): void
    {
        $url = self::BASE . '/works/list/genre/315';
        $result = (new Listing($this->clientWithFixture('ideapocket/list-genre-315.html'), $this->config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(30, $result->items);
        self::assertSame(self::BASE . '/works/detail/IDPC034', $result->items[0]->url);
        $movie = $result->items[1]->meta['movie'];
        self::assertSame('IDPC035', $movie['external_id']);
        self::assertSame('尹秀雅', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('デビュー作品', $movie['metadata']['search_key']);
        self::assertSame('全217作品中 1 〜 30 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame($url . '?page=2', $result->pagination->nextUrl);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $result = (new PerformerDetail($this->clientWithFixture('ideapocket/actress-876796.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/876796'));
        $person = $result->meta['performer'];
        self::assertSame('876796', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/876796', $person['url']);
        self::assertSame('MISAKI YUZUHA', $person['name']);
        self::assertSame('美咲柚葉', $person['name_japanese']);
        self::assertSame(['美咲柚葉', 'MISAKI YUZUHA'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertSame('165cm', $person['height_raw']);
        self::assertSame('B92cm (H) W56cm H90cm', $person['size_raw']);
        self::assertSame(['身長' => '165cm', '3サイズ' => 'B92cm (H) W56cm H90cm', '特技' => 'ピアノ'], $person['raw_profile']);
        self::assertSame('https://cdn.up-timely.com/image/4/actress_main/876796/hiEwKoCO3jf0jhYBAgexRGJmakB7yn2PlltjNoZt.jpg', $person['profile_image_url']);
        self::assertSame([$person['profile_image_url']], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertSame('IDPC034', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/IDPC034', $person['metadata']['works'][0]['url']);
        self::assertSame('FIRST IMPRESSION198 美咲柚葉 30歳 都内病院で働く6年目の現役看護師', $person['metadata']['works'][0]['name']);
        self::assertSame('https://cdn.up-timely.com/image/4/content/88413/JcF4zBz5aiBm8M3oE7nQ1g1n2wxjMUXf5UL8lcv3.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('ideapocket');
        self::assertInstanceOf(IdeapocketCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-12-01' => CrawlType::Listing,
            '/works/list/genre/315' => CrawlType::Listing,
            '/works/list/label/2771' => CrawlType::Listing,
            '/works/list/series/4525' => CrawlType::Listing,
            '/works/detail/IDPC034' => CrawlType::Detail,
            '/actress/detail/876796' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('ideapocket', $crawler->name());
    }

    public function test_shared_base_leaves_s1_output_unchanged(): void
    {
        $result = (new Detail($this->clientWithFixture('s1/detail-SNOS524.html'), S1Site::config()))->execute(new CrawlRequestDto(url: 'https://s1s1s1.com/works/detail/SNOS524'));
        self::assertArrayNotHasKey('label', $result->meta['movie']['metadata']);
        self::assertSame('イナバール', $result->meta['movie']['metadata']['director']);
    }

    private function detail(string $code): \JOOservices\CrawlerX\Dto\CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('ideapocket/detail-' . $code . '.html'), $this->config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/' . $code));
    }

    private function config(): \JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig
    {
        return Site::config();
    }
}
