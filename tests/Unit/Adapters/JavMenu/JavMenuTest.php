<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavMenu;

use JOOservices\CrawlerX\Adapters\JavMenu\JavMenuCrawler;
use JOOservices\CrawlerX\Adapters\JavMenu\Site;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class JavMenuTest extends TestCase
{
    private const BASE = 'https://javmenu.com';

    /** @return array<string, mixed> */
    private function detail(string $fixture, string $path): array
    {
        return (new Detail($this->clientWithFixture('javmenu/' . $fixture)))
            ->execute(new CrawlRequestDto(url: self::BASE . $path, type: CrawlType::Detail))
            ->meta['movie'];
    }

    public function test_detail_fields_from_real_capture(): void
    {
        $movie = $this->detail('detail-DSOD-078.html', '/DSOD-078');

        self::assertSame('DSOD-078', $movie['external_id']);
        self::assertSame('DSOD-078', $movie['code']);
        self::assertSame('【高衩特化】為了讓公司裡最可愛的惡毒巨乳女人人生毀滅，把她矯正成淫叫聲肉便器人格。 桃園怜奈', $movie['title']);
        self::assertSame('2026-11-06', $movie['date']);
        self::assertSame(140, $movie['duration']);
        self::assertNull($movie['description']);
        self::assertSame(['巨乳', '中出', '玩具', '角色扮演者', '單體作品'], $movie['tags']);
        self::assertCount(2, $movie['performers']);
        self::assertSame('83V', $movie['performers'][0]['external_id']);
        self::assertSame('桃園憐奈', $movie['performers'][0]['name']);
        self::assertSame(self::BASE . '/actor/83V', $movie['performers'][0]['url']);
        self::assertSame('0R5na', $movie['performers'][1]['external_id']);
        self::assertSame('堀内ハジメ', $movie['performers'][1]['name']);
    }

    public function test_detail_publisher_metadata_from_real_capture(): void
    {
        $meta = $this->detail('detail-DSOD-078.html', '/DSOD-078')['metadata'];

        self::assertSame('三島六三郎', $meta['director']);
        self::assertSame('8042', $meta['director_id']);
        self::assertSame(self::BASE . '/censored/director/8042', $meta['director_url']);
        self::assertSame('ダスッ！', $meta['maker']);
        self::assertSame('440', $meta['maker_id']);
        self::assertSame(self::BASE . '/censored/maker/440', $meta['maker_url']);
        self::assertSame('【ハイレグ無様特化】おほ声肉便器', $meta['series']);
        self::assertSame('zcka4tsx', $meta['series_id']);
        self::assertSame(self::BASE . '/censored/series/zcka4tsx', $meta['series_url']);
        self::assertSame('DSOD', $meta['code_prefix']);
        self::assertSame(self::BASE . '/code/DSOD', $meta['code_prefix_url']);
        self::assertSame('2026-11-06', $meta['updated_date']);
        self::assertSame('2026-11-06T16:40:44+08:00', $meta['upload_datetime']);
        self::assertTrue($meta['online_available']);
        self::assertTrue($meta['downloadable']);
        self::assertCount(5, $meta['genres']);
        self::assertSame(['id' => '1552', 'name' => '單體作品', 'url' => self::BASE . '/censored/genre/1552'], $meta['genres'][4]);
        self::assertSame(
            [
                '番號' => 'DSOD-078',
                '發佈於' => '2026-11-06',
                '更新於' => '2026-11-06',
                '時長' => '140分鐘',
                '導演' => '三島六三郎',
                '製作' => 'ダスッ！',
                '系列' => '【ハイレグ無様特化】おほ声肉便器',
                '類別' => '巨乳 / 中出 / 玩具 / 角色扮演者 / 單體作品',
                '女優' => '桃園憐奈 / 堀内ハジメ',
            ],
            $meta['source_fields'],
        );
    }

    public function test_detail_images_are_the_poster_only_and_never_a_related_cover(): void
    {
        $movie = $this->detail('detail-DSOD-078.html', '/DSOD-078');

        self::assertSame('https://c0.jdbstatic.com/covers/ox/OXAe6v.jpg', $movie['cover_url']);
        self::assertSame([], $movie['screenshots']);
        $json = (string) json_encode($movie);
        self::assertSame(1, substr_count($json, 'jdbstatic.com'));
        self::assertStringNotContainsString('PPPE-064', $json);
        self::assertStringNotContainsString('fmtu.sl2025p.com', $json);
    }

    public function test_detail_magnets_streams_and_alternates_from_real_capture(): void
    {
        $meta = $this->detail('detail-DSOD-078.html', '/DSOD-078')['metadata'];

        self::assertCount(3, $meta['magnets']);
        self::assertSame('DSOD-078-U.无码破解', $meta['magnets'][0]['name']);
        self::assertSame(
            'magnet:?xt=urn:btih:30677c6d95ac8903b1f04ed006484cfa8fabfec0&dn=[javdb.com]DSOD-078-U.无码破解.torrent',
            $meta['magnets'][0]['url'],
        );
        self::assertSame('2026-09-05', $meta['magnets'][0]['date']);
        self::assertSame(['高清'], $meta['magnets'][0]['tags']);
        self::assertSame(['高清', '5個文件'], $meta['magnets'][1]['tags']);
        self::assertSame('2026-09-03', $meta['magnets'][2]['date']);
        self::assertSame([['name' => '線路 1', 'url' => 'https://2608.xbv2608.com/20261104/oiIGg0My/index.m3u8']], $meta['stream_urls']);
        self::assertCount(10, $meta['alternate_urls']);
        self::assertSame(self::BASE . '/en/DSOD-078', $meta['alternate_urls']['en']);
        self::assertSame(self::BASE . '/DSOD-078', $meta['alternate_urls']['zh-TW']);
    }

    public function test_detail_without_idols_maker_or_magnets_keeps_only_what_the_page_offers(): void
    {
        $movie = $this->detail('detail-ABF-386.html', '/ABF-386');

        self::assertSame('ABF-386', $movie['code']);
        self::assertSame('美巨乳女友被性慾完全支配的變態情侶的同居內射性交實錄。 三佳詩', $movie['title']);
        self::assertSame('2026-11-06', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame([], $movie['performers']);
        self::assertSame([], $movie['tags']);
        self::assertSame('https://666.9989641.xyz/video/m3u8/2026/10/13/86f2a034/vod.jpg', $movie['cover_url']);
        self::assertArrayNotHasKey('maker', $movie['metadata']);
        self::assertArrayNotHasKey('magnets', $movie['metadata']);
        self::assertFalse($movie['metadata']['downloadable']);
        self::assertSame(
            [
                ['name' => '線路 1', 'url' => 'https://2608.xbv2608.com/20261104/YpVbIYeE/index.m3u8'],
                ['name' => '線路 2', 'url' => 'https://t27.cdn2020.com/video/m3u8/2026/10/13/86f2a034/index.m3u8'],
            ],
            $movie['metadata']['stream_urls'],
        );
    }

    public function test_english_page_gives_the_same_fields_with_english_names(): void
    {
        $movie = $this->detail('detail-DSOD-078-en.html', '/en/DSOD-078');

        self::assertSame('DSOD-078', $movie['external_id']);
        self::assertSame('2026-11-06', $movie['date']);
        self::assertSame(140, $movie['duration']);
        self::assertSame(['Big Tits', 'Creampie', 'Toy', 'Cosplayers', 'Single Work'], $movie['tags']);
        self::assertSame(self::BASE . '/en/actor/83V', $movie['performers'][0]['url']);
        self::assertSame('83V', $movie['performers'][0]['external_id']);
        self::assertSame('ダスッ！', $movie['metadata']['maker']);
        self::assertSame(self::BASE . '/en/censored/maker/440', $movie['metadata']['maker_url']);
        self::assertSame('440', $movie['metadata']['maker_id']);
        self::assertSame('三島六三郎', $movie['metadata']['director']);
        self::assertSame('zcka4tsx', $movie['metadata']['series_id']);
        self::assertSame('Big Tits', $movie['metadata']['genres'][0]['name']);
        self::assertSame(self::BASE . '/en/code/DSOD', $movie['metadata']['code_prefix_url']);
        self::assertSame('140minutes', $movie['metadata']['source_fields']['Duration']);
        self::assertSame('Big Tits / Creampie / Toy / Cosplayers / Single Work', $movie['metadata']['source_fields']['Genre']);
        self::assertCount(3, $movie['metadata']['magnets']);
    }

    public function test_work_listing_cards_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('javmenu/list-censored-page2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/censored?page=2', type: CrawlType::Listing, page: 2));

        self::assertSame('movie', $result->entityType);
        self::assertSame(2, $result->page);
        self::assertCount(23, $result->items);
        self::assertSame(self::BASE . '/SAN-078', $result->items[0]->url);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('SAN-078', $first['external_id']);
        self::assertSame('SAN-078', $first['code']);
        self::assertSame('義理の息子に繰り返しレ●プされ完全服従してしまった継母 中条莉乃', $first['title']);
        self::assertSame('2026-11-07', $first['date']);
        self::assertSame('https://fmtu.sl2025p.com/upload/vod/20230203-1/c3286c421d6a8e3c9d8b81f75d4f9363.jpg', $first['cover_url']);
        self::assertTrue($first['metadata']['online_available']);
        self::assertFalse($first['metadata']['downloadable']);
        self::assertTrue($first['metadata']['is_new']);
        self::assertStringNotContainsString('t.me', (string) json_encode($result->items));
        self::assertStringNotContainsString('/theme/', (string) json_encode($result->items));
    }

    public function test_work_listing_pagination_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('javmenu/list-censored-page2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/censored?page=2', type: CrawlType::Listing, page: 2));

        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(25193, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/censored?page=3', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_maker_listing_is_the_same_grid(): void
    {
        $result = (new Listing($this->clientWithFixture('javmenu/list-maker-440.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/censored/maker/440', type: CrawlType::Listing));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(self::BASE . '/censored/maker/440?page=2', $result->pagination->nextUrl);
        self::assertSame('DSOD-078', $result->items[0]->meta['movie']['external_id']);
        self::assertCount(23, $result->items);
    }

    public function test_performer_listing_cards_from_real_capture(): void
    {
        $result = (new PerformerListing($this->clientWithFixture('javmenu/actor-list.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/censored/actor', type: CrawlType::PerformerListing));

        self::assertSame('performer', $result->entityType);
        self::assertCount(48, $result->items);
        self::assertSame(self::BASE . '/actor/MmNVR', $result->items[0]->url);
        $first = $result->items[0]->meta['performer'];
        self::assertSame('MmNVR', $first['external_id']);
        self::assertSame('白谷葵', $first['name']);
        self::assertNull($first['profile_image_url']);
        self::assertSame('k4MWJ', $result->items[1]->meta['performer']['external_id']);
        self::assertSame('瀬川玲奈', $result->items[1]->meta['performer']['name']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1512, $result->pagination->lastPage);
        self::assertSame(self::BASE . '/censored/actor?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_performer_page_name_and_work_grid_from_real_capture(): void
    {
        $item = (new PerformerDetail($this->clientWithFixture('javmenu/actor-83V.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/actor/83V', type: CrawlType::PerformerDetail));
        $performer = $item->meta['performer'];

        self::assertSame('performer', $item->entityType);
        self::assertSame('83V', $performer['external_id']);
        self::assertSame('桃園憐奈', $performer['name']);
        self::assertSame(self::BASE . '/actor/83V', $performer['url']);
        self::assertNull($performer['profile_image_url']);
        self::assertSame(['heading' => '最新桃園憐奈影片'], $performer['raw_profile']);
        $works = $performer['metadata']['works'];
        self::assertCount(23, $works);
        self::assertSame('DSOD-078', $works[0]['external_id']);
        self::assertSame('DSOD-078', $works[0]['code']);
        self::assertSame(self::BASE . '/DSOD-078', $works[0]['url']);
        self::assertSame('【高衩特化】為了讓公司裡最可愛的惡毒巨乳女人人生毀滅，把她矯正成淫叫聲肉便器人格。 桃園怜奈', $works[0]['title']);
        self::assertSame('2026-11-06', $works[0]['date']);
        self::assertSame('https://c0.jdbstatic.com/covers/ox/OXAe6v.jpg', $works[0]['cover_url']);
        self::assertTrue($works[0]['downloadable']);
        self::assertSame(1, $performer['metadata']['works_page']);
        self::assertSame(self::BASE . '/actor/83V?page=2', $performer['metadata']['works_next_url']);
        self::assertGreaterThan(1, $performer['metadata']['works_last_page']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('javmenu');
        self::assertInstanceOf(JavMenuCrawler::class, $crawler);
        $expected = [
            '/DSOD-078' => CrawlType::Detail,
            '/en/DSOD-078' => CrawlType::Detail,
            '/FC2-4969519' => CrawlType::Detail,
            '/W212167' => CrawlType::Detail,
            '/censored' => CrawlType::Listing,
            '/censored?page=2' => CrawlType::Listing,
            '/ja/uncensored' => CrawlType::Listing,
            '/code/DSOD' => CrawlType::Listing,
            '/censored/maker/440' => CrawlType::Listing,
            '/censored/series/zcka4tsx' => CrawlType::Listing,
            '/censored/genre/17' => CrawlType::Listing,
            '/censored/director/8042' => CrawlType::Listing,
            '/censored/actor' => CrawlType::PerformerListing,
            '/zh/western/actor?page=3' => CrawlType::PerformerListing,
            '/actor/83V' => CrawlType::PerformerDetail,
            '/en/actor/83V?page=2' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/rank/censored/day')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/en')->matched);
        self::assertSame('javmenu', $crawler->name());
    }

    public function test_site_ids_ignore_the_language_prefix_and_foreign_hosts(): void
    {
        self::assertSame('DSOD-078', Site::workId(self::BASE . '/zh_TW/DSOD-078'));
        self::assertSame('83V', Site::performerId(self::BASE . '/ko/actor/83V'));
        self::assertNull(Site::workId('https://t.me/DSOD-078'));
        self::assertNull(Site::workId(self::BASE . '/censored'));
        self::assertNull(Site::performerId(self::BASE . '/censored/actor'));
        self::assertSame('code-1', Site::entityId(self::BASE . '/code/code-1'));
        self::assertSame(3, Site::pageNumber(self::BASE . '/censored?page=3'));
        self::assertNull(Site::pageNumber(self::BASE . '/censored?page=x'));
        self::assertNull(Site::pageNumber(self::BASE . '/censored'));
    }

    public function test_pages_without_the_expected_markup_are_rejected(): void
    {
        $empty = '<html><body><p>nothing</p></body></html>';

        foreach ([Listing::class, PerformerListing::class, Detail::class, PerformerDetail::class] as $type) {
            try {
                (new $type($this->clientWithHtml($empty)))->execute(new CrawlRequestDto(url: self::BASE . '/actor/x', type: CrawlType::Detail));
                self::fail($type . ' accepted an empty page');
            } catch (CrawlParseException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
