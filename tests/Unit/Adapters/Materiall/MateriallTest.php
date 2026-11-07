<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Materiall;

use JOOservices\CrawlerX\Adapters\Materiall\MateriallCrawler;
use JOOservices\CrawlerX\Adapters\Materiall\Site;
use JOOservices\CrawlerX\Adapters\Materiall\Types\Detail;
use JOOservices\CrawlerX\Adapters\Materiall\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class MateriallTest extends TestCase
{
    private const BASE = 'https://materiall.jp';

    private const UPLOADS = 'https://materiall.jp/wp/wp-content/uploads';

    /**
     * @return array<string, mixed>
     */
    private function detail(string $slug): array
    {
        $result = (new Detail($this->clientWithFixture("materiall/detail-{$slug}.html")))
            ->execute(new CrawlRequestDto(url: self::BASE . "/works/{$slug}/"));
        self::assertSame(self::BASE . "/works/{$slug}/", $result->url);
        self::assertSame('movie', $result->entityType);

        return $result->meta['movie'];
    }

    public function test_detail_extracts_every_field_of_a_single_cast_work(): void
    {
        $movie = $this->detail('mtall-149');

        self::assertSame('mtall-149', $movie['external_id']);
        self::assertSame('MTALL-149', $movie['code']);
        self::assertSame('2次元好き喪女ニートが3次元の隣家男をオナネタにしてる件', $movie['title']);
        self::assertSame(self::UPLOADS . '/2025/04/MTALL-149_1200.jpg', $movie['cover_url']);
        self::assertStringStartsWith('実家暮らしで無職引きこもりのひかるさん２５歳。', $movie['description']);
        self::assertStringEndsWith('リアルチ〇ポにムラムラが止まらない！', $movie['description']);
        self::assertSame('2025-05-15', $movie['date']);
        self::assertSame(115, $movie['duration']);
        self::assertSame(['皆月ひかる'], array_column($movie['performers'], 'name'));
        self::assertSame([], $movie['tags']);
        self::assertSame([], $movie['screenshots']);

        $metadata = $movie['metadata'];
        self::assertSame('Materiall', $metadata['maker']);
        self::assertSame('シンしばはら', $metadata['director']);
        self::assertSame('2025-04-22', $metadata['reservation_start_date']);
        self::assertSame('2025-05-15', $metadata['distribution_date']);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=1mtall00149/size=720_480/', $metadata['sample_video_url']);
        self::assertCount(1, $metadata['purchase_links']);
        self::assertSame('fanza', $metadata['purchase_links'][0]['store']);
        self::assertSame('FANZAで視聴', $metadata['purchase_links'][0]['label']);
        self::assertSame('2025-04-22', $metadata['purchase_links'][0]['available_from']);
        self::assertStringContainsString('cid%3D1mtall149', $metadata['purchase_links'][0]['url']);
        self::assertSame([
            '出演女優' => '皆月ひかる',
            '監督' => 'シンしばはら',
            '収録時間' => '115分',
            '予約開始日' => '2025/04/22',
            '配信開始日' => '2025/05/15',
        ], $metadata['source_fields']);
        foreach (['label', 'series', 'price', 'release_date'] as $absent) {
            self::assertArrayNotHasKey($absent, $metadata);
        }
    }

    public function test_detail_of_a_compilation_has_no_cast_or_director(): void
    {
        $movie = $this->detail('mtafb-008');

        self::assertSame('MTAFB-008', $movie['code']);
        self::assertSame('【福袋】男の欲望のなすがままに、●されていく女たち 18時間ノーカット 1114分', $movie['title']);
        self::assertSame(self::UPLOADS . '/2026/06/MTAFB-008_1200.jpg', $movie['cover_url']);
        self::assertSame(1114, $movie['duration']);
        self::assertSame('2026-06-25', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertArrayNotHasKey('director', $movie['metadata']);
        self::assertArrayNotHasKey('sample_video_url', $movie['metadata']);
        self::assertSame(
            'https://video.dmm.co.jp/av/content/?id=1mtafb00008&i3_ref=search&i3_ord=1&i3_pst=1',
            $movie['metadata']['purchase_links'][0]['url'],
        );
        self::assertSame(
            ['収録時間' => '1114分', '予約開始日' => '2026/06/25', '配信開始日' => '2026/06/25'],
            $movie['metadata']['source_fields'],
        );
    }

    public function test_detail_ignores_page_chrome_and_ad_images(): void
    {
        $encoded = (string) json_encode($this->detail('mtall-149'), JSON_UNESCAPED_UNICODE);

        foreach (['toplogo', 'img_play', 'long_banner', 'gifbanner', 'valuecommerce', 'プライバシー', '18歳以上'] as $noise) {
            self::assertStringNotContainsString($noise, $encoded);
        }
    }

    public function test_listing_extracts_every_card_field(): void
    {
        $result = (new Listing($this->clientWithFixture('materiall/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(12, $result->items);

        $first = $result->items[0];
        self::assertSame(self::BASE . '/works/mtafb-008/', $first->url);
        $movie = $first->meta['movie'];
        self::assertSame('mtafb-008', $movie['external_id']);
        self::assertSame('MTAFB-008', $movie['code']);
        self::assertSame('【福袋】男の欲望のなすがままに、●されていく女たち 18時間ノーカット 1114分', $movie['title']);
        self::assertSame(self::UPLOADS . '/2026/06/MTAFB-008_sum.jpg', $movie['cover_url']);
        self::assertSame('2026-06-25', $movie['date']);
        self::assertSame([], $movie['performers']);
        self::assertSame('Materiall', $movie['metadata']['maker']);
        self::assertSame('2026-06-25', $movie['metadata']['reservation_start_date']);
        self::assertSame('2026-06-25', $movie['metadata']['distribution_date']);
        self::assertSame(['fanza'], array_column($movie['metadata']['purchase_links'], 'store'));
        self::assertSame('2026-06-25', $movie['metadata']['purchase_links'][0]['available_from']);

        $multi = $result->items[2]->meta['movie'];
        self::assertSame('mtafb-006', $multi['external_id']);
        self::assertSame(['松本いちか', '森日向子', '倉本すみれ'], array_column($multi['performers'], 'name'));
        self::assertSame(self::UPLOADS . '/2026/02/MTAFB-006_sum.jpg', $multi['cover_url']);
        self::assertSame('2026-02-25', $multi['date']);

        $last = $result->items[11]->meta['movie'];
        self::assertSame('mtall-149', $last['external_id']);
        self::assertSame(['皆月ひかる'], array_column($last['performers'], 'name'));
        self::assertSame(self::UPLOADS . '/2025/04/MTALL-149_2125.jpg', $last['cover_url']);
        self::assertSame('2025-05-15', $last['date']);
        self::assertSame('2025-04-22', $last['metadata']['reservation_start_date']);
    }

    public function test_listing_excludes_tracking_pixels_and_chrome(): void
    {
        $result = (new Listing($this->clientWithFixture('materiall/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'));
        $encoded = (string) json_encode($result->items[0]->meta['movie'], JSON_UNESCAPED_UNICODE);

        foreach (['gifbanner', 'toplogo', '作品タグ一覧'] as $noise) {
            self::assertStringNotContainsString($noise, $encoded);
        }
    }

    public function test_listing_paginates_with_wp_pagenavi(): void
    {
        $pagination = (new Listing($this->clientWithFixture('materiall/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'))
            ->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(15, $pagination->lastPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(self::BASE . '/work/page/2/', $pagination->nextUrl);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_listing_without_cards_fails(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><p>empty</p></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'));
    }

    public function test_last_page_without_next_link_has_no_next_url(): void
    {
        $html = (string) preg_replace(
            '#<a class="nextpostslink".*?</a>#s',
            '',
            $this->loadFixture('materiall/list-work.html'),
        );
        $pagination = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/'))
            ->pagination;

        self::assertFalse($pagination->hasNextPage);
        self::assertNull($pagination->nextUrl);
        self::assertNull($pagination->nextPage);
    }

    public function test_site_helpers(): void
    {
        self::assertSame('mtall-149', Site::externalId(self::BASE . '/works/mtall-149/'));
        self::assertNull(Site::externalId(self::BASE . '/work/'));
        self::assertSame('MTALL-149', Site::code('mtall-149'));
        self::assertSame(['A', 'B', 'C', 'D'], Site::castNames('A B、C　D'));
        self::assertSame([], Site::castNames(null));
        self::assertSame('Title', Site::cleanTitle('Title | Materiall | AVメーカー'));
        self::assertNull(Site::cleanTitle(' | Materiall'));
        self::assertNull(Site::cleanTitle(null));
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('materiall');
        self::assertInstanceOf(MateriallCrawler::class, $crawler);

        $expected = [
            '/work/' => CrawlType::Listing,
            '/work/page/2/' => CrawlType::Listing,
            '/works/mtall-149/' => CrawlType::Detail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/search/')->matched);
        self::assertSame('materiall', $crawler->name());
    }
}
