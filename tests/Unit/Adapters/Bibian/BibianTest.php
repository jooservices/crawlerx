<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bibian;

use JOOservices\CrawlerX\Adapters\Bibian\BibianCrawler;
use JOOservices\CrawlerX\Adapters\Bibian\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class BibianTest extends TestCase
{
    private const BASE = 'https://bibian-av.com';
    private const IMAGE_DIR = 'https://cdn.up-timely.com/image/24/content/88105/';

    public function test_detail_fields_from_real_capture(): void
    {
        $client = $this->clientWithFixture('bibian/detail-BBAN612.html');
        $result = (new Detail($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/BBAN612'));
        $movie = $result->meta['movie'];
        self::assertSame(self::BASE . '/works/detail/BBAN612', $result->url);
        self::assertSame('BBAN612', $movie['external_id']);
        self::assertSame('BBAN612', $movie['code']);
        self::assertSame('自慰よりも気持ちいいこと…女教師がレズ沼に嵌まらせる 角オナ女生徒レズ快楽教育', $movie['title']);
        self::assertStringStartsWith('【花守夏歩 初本格レズ作品？】「どこでそんなエッチなことを覚えたの？」', $movie['description']);
        self::assertStringEndsWith('花守夏歩『初めての本格レズ』作品にガチ蕩けッ！！', $movie['description']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame(130, $movie['duration']);
        self::assertSame(self::IMAGE_DIR . 'yl6d9stVgouTcvunGMRP0Sn6Jk9plyKbBhShE3yn.jpg', $movie['cover_url']);
        self::assertCount(13, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertSame(self::IMAGE_DIR . 'bo21EDrQw8oynAZxYX5xjdICdXd2h8as713OdZog.jpg', $movie['screenshots'][11]['url']);
        self::assertSame(self::IMAGE_DIR . 'I7nLh3twRZlk6l53sbhBSdlXBUi5ZxWXHYnw7ZoB.jpg', $movie['screenshots'][12]['url']);
        self::assertCount(2, $movie['performers']);
        self::assertSame('851168', $movie['performers'][0]['external_id']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name_japanese']);
        self::assertSame(self::BASE . '/actress/detail/851168', $movie['performers'][0]['url']);
        self::assertSame('844893', $movie['performers'][1]['external_id']);
        self::assertSame('天美めあ', $movie['performers'][1]['name']);
        self::assertSame(['学園もの', 'レズキス', 'オナニー', '制服', 'レズ'], $movie['tags']);
        $meta = $movie['metadata'];
        self::assertSame('BIBIAN', $meta['maker']);
        self::assertSame('アズマックス', $meta['director']);
        self::assertSame([], $meta['series']);
        self::assertSame([['name' => 'ビビアン', 'url' => self::BASE . '/works/list/label/9469', 'external_id' => '9469']], $meta['label']);
        self::assertSame([['name' => '2026年11月10日', 'url' => self::BASE . '/works/list/date/2026-11-10', 'external_id' => '2026-11-10']], $meta['release_links']);
        self::assertCount(5, $meta['genres']);
        self::assertSame('352', $meta['genres'][0]['external_id']);
        self::assertSame(self::BASE . '/works/list/genre/111', $meta['genres'][4]['url']);
        self::assertSame([['format' => 'DVD', 'code' => 'BBAN612', 'duration' => 130, 'price' => '3,498円（税込）']], $meta['formats']);
        self::assertSame('https://cc3001.dmm.co.jp/pv/MAN7EgUyID2gVVis_eFKi5E8FuqF2GUTWVCnGgNa1jzhoW4C-3gGpEza8QCUPf/bban00612dm.mp4', $meta['sample_video_url']);
        self::assertSame(self::IMAGE_DIR . 'sample_video_image_file/LbaeeOXkS7YBF7ruN5Dme6pSp2EoAvec6lQ4h263.jpg', $meta['sample_video_poster_url']);
        self::assertCount(2, $meta['purchase_links']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=bban00612/WILLaffi-028', $meta['purchase_links'][0]['url']);
        self::assertSame('DVDを購入 定価 3,498円（税込）', $meta['purchase_links'][1]['name']);
        self::assertSame('※配信日と発売日が異なる場合があります', $meta['release_note']);
        self::assertSame(['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($meta['source_fields']));
        self::assertSame('女優 花守夏歩 天美めあ', $meta['source_fields']['女優']);
        self::assertSame('発売日 2026年11月10日', $meta['source_fields']['発売日']);
        self::assertSame('シリーズ', $meta['source_fields']['シリーズ']);
        self::assertSame('レーベル ビビアン', $meta['source_fields']['レーベル']);
        self::assertSame('ジャンル 学園もの レズキス オナニー 制服 レズ', $meta['source_fields']['ジャンル']);
        self::assertSame('監督 アズマックス', $meta['source_fields']['監督']);
        self::assertSame('品番 DVDBBAN612', $meta['source_fields']['品番']);
        self::assertSame('収録時間 DVD130分', $meta['source_fields']['収録時間']);
        self::assertSame('価格 DVD3,498円（税込）', $meta['source_fields']['価格']);
        self::assertStringNotContainsString('BBAN614', (string) json_encode($movie));
    }

    public function test_date_listing_cards_and_index_walk(): void
    {
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->loadFixture('bibian/dates.html')],
            self::BASE . '/works/list/date/2026-11-10' => ['body' => $this->loadFixture('bibian/list-2026-11-10.html')],
        ]);
        $result = (new Listing($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(5, $result->items);
        self::assertSame(self::BASE . '/works/detail/BBAN612', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('BBAN612', $movie['external_id']);
        self::assertSame('BBAN612', $movie['code']);
        self::assertSame('角オナ女生徒レズ快楽教育', $movie['title']);
        self::assertSame(self::IMAGE_DIR . 'Bs4vg386uu6frhIKE8HzCFEHb9HBsSe5YXoDT3uB.jpg', $movie['cover_url']);
        self::assertSame('2026-11-10', $movie['date']);
        self::assertSame(['851168', '844893'], array_column($movie['performers'], 'external_id'));
        self::assertSame(['花守夏歩', '天美めあ'], array_column($movie['performers'], 'name'));
        self::assertSame(5, $movie['metadata']['release_group']['count']);
        self::assertSame('2026-11-10', $movie['metadata']['release_group']['date']);
        self::assertSame(self::BASE . '/works/list/date/2026-11-10', $movie['metadata']['release_group']['url']);
        self::assertSame('全5作品中 1 〜 5 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertSame('発売日検索結果：2026年11月10日', $movie['metadata']['search_key']);
        self::assertSame('BBAN614', $result->items[1]->meta['movie']['external_id']);
        self::assertSame('BBSS107', $result->items[4]->meta['movie']['external_id']);
        self::assertTrue($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
    }

    public function test_date_listing_page_directly(): void
    {
        $url = self::BASE . '/works/list/date/2026-11-10';
        $result = (new Listing($this->clientWithFixture('bibian/list-2026-11-10.html'), Site::config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(5, $result->items);
        self::assertSame('2026-11-10', $result->items[0]->meta['movie']['date']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_genre_listing_cards(): void
    {
        $url = self::BASE . '/works/list/genre/352';
        $result = (new Listing($this->clientWithFixture('bibian/list-genre-352.html'), Site::config()))->execute(new CrawlRequestDto(url: $url));
        self::assertCount(6, $result->items);
        self::assertSame(self::BASE . '/works/detail/BBAN612', $result->items[0]->url);
        $movie = $result->items[0]->meta['movie'];
        self::assertSame('角オナ女生徒レズ快楽教育', $movie['title']);
        self::assertSame('花守夏歩', $movie['performers'][0]['name']);
        self::assertNull($movie['date']);
        self::assertSame('学園もの', $movie['metadata']['search_key']);
        self::assertSame('全6作品中 1 〜 6 タイトルを表示', $movie['metadata']['listing_range']);
        self::assertFalse($result->pagination->hasNextPage);
    }

    public function test_actress_profile_fields_from_real_capture(): void
    {
        $client = $this->clientWithFixture('bibian/actress-851168.html');
        $result = (new PerformerDetail($client, Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/actress/detail/851168'));
        $person = $result->meta['performer'];
        $image = 'https://cdn.up-timely.com/image/24/actress_main/851168/I9bu2rPLAeOvfx9sGhN7dXydfLoTR1B06fGFhzTL.jpg';
        self::assertSame('851168', $person['external_id']);
        self::assertSame(self::BASE . '/actress/detail/851168', $person['url']);
        self::assertSame('HANAMORI KAHO', $person['name']);
        self::assertSame('花守夏歩', $person['name_japanese']);
        self::assertSame(['花守夏歩', 'HANAMORI KAHO'], $person['aliases']);
        self::assertNull($person['birth_date_raw']);
        self::assertNull($person['height_raw']);
        self::assertNull($person['size_raw']);
        self::assertSame([], $person['raw_profile']);
        self::assertSame($image, $person['profile_image_url']);
        self::assertSame([$image], $person['metadata']['images']);
        self::assertSame([], $person['metadata']['profile_links']);
        self::assertSame([], $person['metadata']['social_links']);
        self::assertCount(1, $person['metadata']['works']);
        self::assertSame('BBAN612', $person['metadata']['works'][0]['external_id']);
        self::assertSame(self::BASE . '/works/detail/BBAN612', $person['metadata']['works'][0]['url']);
        self::assertSame('角オナ女生徒レズ快楽教育', $person['metadata']['works'][0]['name']);
        self::assertSame(self::IMAGE_DIR . 'Bs4vg386uu6frhIKE8HzCFEHb9HBsSe5YXoDT3uB.jpg', $person['metadata']['works'][0]['cover_url']);
        self::assertSame('全1作品中 1 〜 1 タイトルを表示', $person['metadata']['work_range']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('bibian');
        self::assertInstanceOf(BibianCrawler::class, $crawler);
        $expected = [
            '/works/date' => CrawlType::Listing,
            '/works/list/date/2026-11-10' => CrawlType::Listing,
            '/works/list/genre/352' => CrawlType::Listing,
            '/works/list/label/9469' => CrawlType::Listing,
            '/works/detail/BBAN612' => CrawlType::Detail,
            '/actress/detail/851168' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
        self::assertSame('bibian', $crawler->name());
    }
}
