<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Falenogroup;

use JOOservices\CrawlerX\Adapters\Falenogroup\FalenogroupCrawler;
use JOOservices\CrawlerX\Adapters\Falenogroup\Site;
use JOOservices\CrawlerX\Adapters\Falenogroup\Types\Detail;
use JOOservices\CrawlerX\Adapters\Falenogroup\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class FalenogroupTest extends TestCase
{
    private const BASE = 'https://falenogroup.com';

    private const CDN = 'https://cdn.faleno.net/group/wp-content/uploads';

    /**
     * @return array<string, mixed>
     */
    private function detail(string $slug): array
    {
        $result = (new Detail($this->clientWithFixture("falenogroup/detail-{$slug}.html")))
            ->execute(new CrawlRequestDto(url: self::BASE . "/works/{$slug}/"));
        self::assertSame(self::BASE . "/works/{$slug}/", $result->url);
        self::assertSame('movie', $result->entityType);

        return $result->meta['movie'];
    }

    public function test_detail_extracts_every_field_of_a_distribution_only_work(): void
    {
        $movie = $this->detail('fthtd-243');

        self::assertSame('fthtd-243', $movie['external_id']);
        self::assertSame('FTHTD-243', $movie['code']);
        self::assertSame('朝起きたら見知らぬ女子大生と全裸密着…微かな記憶を辿りながら思い出した快楽の一夜', $movie['title']);
        self::assertSame(self::CDN . '/2026/10/FTHTD-243_1200.jpg', $movie['cover_url']);
        self::assertStringStartsWith('この人は一体誰？衝撃の目覚めから話が進んでいく本作。', $movie['description']);
        self::assertStringEndsWith('天真爛漫JDと冴えないサラリーマンのひと夏の物語。', $movie['description']);
        self::assertSame('2026-10-23', $movie['date']);
        self::assertSame(123, $movie['duration']);
        self::assertSame(['結月りあ'], array_column($movie['performers'], 'name'));
        self::assertSame([], $movie['tags']);
        self::assertCount(15, $movie['screenshots']);
        self::assertSame(self::CDN . '/2026/10/FTHTD-243_1.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . '/2026/10/FTHTD-243_1-300x169.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(1, $movie['screenshots'][0]['metadata']['position']);
        self::assertSame(self::CDN . '/2026/10/FTHTD-243_15.jpg', $movie['screenshots'][14]['url']);
        self::assertSame(15, $movie['screenshots'][14]['metadata']['position']);

        $metadata = $movie['metadata'];
        self::assertSame('FALENO TUBE', $metadata['maker']);
        self::assertSame('2026-10-23', $metadata['distribution_date']);
        self::assertSame(self::CDN . '/2026/10/FTHTD-243_PR.mp4', $metadata['sample_video_url']);
        self::assertSame([
            '出演女優' => '結月りあ',
            '収録時間' => '123分',
            'メーカー' => 'FALENO TUBE',
            '配信開始日' => '2026/10/23',
        ], $metadata['source_fields']);
        foreach (['director', 'release_date', 'release_note', 'purchase_links', 'label', 'series', 'price'] as $absent) {
            self::assertArrayNotHasKey($absent, $metadata);
        }
    }

    public function test_detail_extracts_release_date_and_store_links(): void
    {
        $movie = $this->detail('noskn-106');

        self::assertSame('noskn-106', $movie['external_id']);
        self::assertSame('NOSKN-106', $movie['code']);
        self::assertSame('【ALL無編集】NO cut！NO SKIN！Nakadashi！完全ノーカットで魅せる真性中出しガチイキ映像　由良かな', $movie['title']);
        self::assertSame(self::CDN . '/2026/11/NOSKN-106_package.jpg', $movie['cover_url']);
        self::assertSame('ALL半中半外！完全ノーカット中出し！圧倒的美女優の最強生痴態を見せるのは由良かなちゃん。合法●リの最高峰・由良かな降臨！！ちっちゃミニマムボディで巨根たちを生抜き倒すっ！！', $movie['description']);
        self::assertSame('2026-12-04', $movie['date']);
        self::assertSame(151, $movie['duration']);
        self::assertSame(['由良かな'], array_column($movie['performers'], 'name'));
        self::assertCount(17, $movie['screenshots']);
        self::assertSame(self::CDN . '/2026/11/NOSKN-106_s_1-212x300.jpg', $movie['screenshots'][0]['thumbnail_url']);

        $metadata = $movie['metadata'];
        self::assertSame('ノースキンズ', $metadata['maker']);
        self::assertSame('2026-12-04', $metadata['release_date']);
        self::assertSame('2026-11-06', $metadata['distribution_date']);
        self::assertSame('2026/12/4 発売開始', $metadata['release_note']);
        self::assertSame(self::CDN . '/2026/11/NOSKN-106_PRLQ.mp4', $metadata['sample_video_url']);
        self::assertSame([
            [
                'store' => 'hnext',
                'label' => 'H-NEXTで視聴',
                'url' => 'https://landing.hnext.jp/media/U13JMQRCFC',
                'available_from' => '2026-11-06',
            ],
            [
                'store' => 'fanza',
                'label' => 'FANZAで購入',
                'url' => 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=1noskn00106/',
                'available_from' => '2026-12-04',
            ],
        ], $metadata['purchase_links']);
        self::assertSame([
            '出演女優' => '由良かな',
            '収録時間' => '151分',
            'メーカー' => 'ノースキンズ',
            '配信開始日' => '2026/11/6',
            '発売日' => '2026/12/4',
        ], $metadata['source_fields']);
    }

    public function test_detail_extracts_director(): void
    {
        $movie = $this->detail('ftkd-046');

        self::assertSame('FTKD-046', $movie['code']);
        self::assertSame('皆月ひかる', $movie['performers'][0]['name']);
        self::assertSame(139, $movie['duration']);
        self::assertSame('2026-10-23', $movie['date']);
        self::assertSame('こばやし はちまん', $movie['metadata']['director']);
        self::assertSame('FALENO TUBE', $movie['metadata']['maker']);
        self::assertSame('こばやし はちまん', $movie['metadata']['source_fields']['監督']);
    }

    public function test_detail_ignores_related_works_and_page_chrome(): void
    {
        $movie = $this->detail('fthtd-243');
        $encoded = (string) json_encode($movie, JSON_UNESCAPED_UNICODE);

        foreach (['fthtd-230', 'RELATED WORKS', 'メーカー一覧', 'twitter', '18歳以上'] as $noise) {
            self::assertStringNotContainsString($noise, $encoded);
        }
    }

    public function test_listing_extracts_every_card_field(): void
    {
        $result = (new Listing($this->clientWithFixture('falenogroup/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(28, $result->items);

        $first = $result->items[0];
        self::assertSame(self::BASE . '/works/vaiav-035/', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('vaiav-035', $movie['external_id']);
        self::assertSame('VAIAV-035', $movie['code']);
        self::assertSame('浴衣初デートで突然の雨', $movie['title']);
        self::assertSame(self::CDN . '/2026/11/VAIAV-035_2125.jpg', $movie['cover_url']);
        self::assertSame('2026-12-04', $movie['date']);
        self::assertSame('2026/12/4 発売開始', $movie['metadata']['release_note']);
        self::assertSame(self::CDN . '/2022/05/botan.jpg', $movie['metadata']['maker_logo_url']);
        self::assertSame(self::CDN . '/2026/11/VAIAV-035_PR.mp4', $movie['metadata']['sample_video_url']);
        self::assertSame(
            ['hnext', 'fanza'],
            array_column($movie['metadata']['purchase_links'], 'store'),
        );
        self::assertSame(
            'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=1vaiav00035/',
            $movie['metadata']['purchase_links'][1]['url'],
        );
        self::assertArrayNotHasKey('maker', $movie['metadata']);
        self::assertSame([], $movie['performers']);
    }

    public function test_listing_paginates_with_wp_pagenavi(): void
    {
        $pagination = (new Listing($this->clientWithFixture('falenogroup/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'))
            ->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(96, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(self::BASE . '/work/page/2/', $pagination->nextUrl);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_maker_page_lists_its_works_with_the_maker(): void
    {
        $result = (new Listing($this->clientWithFixture('falenogroup/maker-noskn.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/makers/noskn/'));

        self::assertCount(12, $result->items);
        self::assertSame(['noskn-106', 'aiav-056'], array_slice(array_map(
            static fn($item): string => $item->meta['movie']['external_id'],
            $result->items,
        ), 0, 2));
        $metadata = $result->items[0]->meta['movie']['metadata'];
        self::assertSame('ノースキンズ', $metadata['maker']);
        self::assertSame(self::CDN . '/2022/05/noskins.jpg?resize=520:*&output-quality=60', $metadata['maker_logo_url']);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertSame(1, $result->pagination->lastPage);
    }

    public function test_listing_without_cards_fails(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><p>empty</p></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'));
    }

    public function test_site_reads_ids_from_urls(): void
    {
        self::assertSame('fthtd-243', Site::externalId(self::BASE . '/works/fthtd-243/'));
        self::assertNull(Site::externalId(self::BASE . '/work/'));
        self::assertSame('FTHTD-243', Site::code('fthtd-243'));
        self::assertTrue(Site::isMakerPage(self::BASE . '/makers/noskn/'));
        self::assertFalse(Site::isMakerPage(self::BASE . '/makers/'));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('falenogroup');
        self::assertInstanceOf(FalenogroupCrawler::class, $crawler);

        $expected = [
            '/work/' => CrawlType::Listing,
            '/work/page/2/' => CrawlType::Listing,
            '/makers/noskn/' => CrawlType::Listing,
            '/works/fthtd-243/' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/makers/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/genre/')->matched);
        self::assertSame('falenogroup', $crawler->name());
    }

    public function test_original_image_drops_the_cdn_recompression_parameters(): void
    {
        self::assertSame('https://cdn.example.test/a.jpg', Site::originalImage('https://cdn.example.test/a.jpg?output-quality=60'));
        self::assertSame('https://cdn.example.test/a.jpg', Site::originalImage('https://cdn.example.test/a.jpg?resize=470:*&output-quality=60'));
        self::assertSame('https://cdn.example.test/a.jpg?v=2', Site::originalImage('https://cdn.example.test/a.jpg?v=2&output-quality=60'));
        self::assertSame('https://cdn.example.test/a.jpg', Site::originalImage('https://cdn.example.test/a.jpg'));
    }
}
