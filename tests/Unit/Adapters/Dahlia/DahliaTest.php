<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Dahlia;

use JOOservices\CrawlerX\Adapters\Dahlia\DahliaCrawler;
use JOOservices\CrawlerX\Adapters\Dahlia\Site;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\Detail;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\Listing;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Dahlia\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class DahliaTest extends TestCase
{
    private const BASE = 'https://dahlia-av.jp';

    private const CDN = 'https://cdn.faleno.net/dahlia/wp-content/uploads/2026/10/';

    /** @return array<string, mixed> */
    private function detail(): array
    {
        return (new Detail($this->clientWithFixture('dahlia/detail-DLDSS-573.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/works/dldss573/', type: CrawlType::Detail))
            ->meta['movie'];
    }

    public function test_detail_fields_from_real_capture(): void
    {
        $movie = $this->detail();

        self::assertSame('dldss573', $movie['external_id']);
        self::assertSame('DLDSS-573', $movie['code']);
        self::assertSame('子育てが落ち着いたので今は近所の⚫︎生に私が育てられています。 宮上唯依花', $movie['title']);
        self::assertStringStartsWith('子育てがひと段落し、自分の時間が少しずつでき', $movie['description']);
        self::assertStringEndsWith('まるでペットのように躾けていき…。', $movie['description']);
        self::assertSame('2026-12-03', $movie['date']);
        self::assertSame(135, $movie['duration']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('宮上唯依花', $movie['performers'][0]['name']);
    }

    public function test_detail_images_cover_poster_and_every_gallery_still_with_thumbnails(): void
    {
        $movie = $this->detail();

        self::assertSame(self::CDN . 'DLDSS-573_1200.jpg', $movie['cover_url']);
        self::assertSame(self::CDN . 'DLDSS-573_PR.mp4', $movie['metadata']['sample_video_url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame(self::CDN . 'fe28205f708c36aa7cd6d4fa6f5bafef.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'fe28205f708c36aa7cd6d4fa6f5bafef-400x400.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(1, $movie['screenshots'][0]['metadata']['position']);
        self::assertSame(self::CDN . 'b2fd97efae20e8a7ffc06b8f4e4ee645.jpg', $movie['screenshots'][9]['url']);
        self::assertSame(self::CDN . 'b2fd97efae20e8a7ffc06b8f4e4ee645-400x400.jpg', $movie['screenshots'][9]['thumbnail_url']);
        self::assertSame(10, $movie['screenshots'][9]['metadata']['position']);
        self::assertStringNotContainsString('1440-130', (string) json_encode($movie));
        self::assertStringNotContainsString('output-quality', (string) json_encode($movie['screenshots']));
    }

    public function test_detail_metadata_and_labelled_rows_from_real_capture(): void
    {
        $meta = $this->detail()['metadata'];

        self::assertSame('DAHLIA', $meta['maker']);
        self::assertSame('2026-12-03', $meta['release_date']);
        self::assertSame('2026-11-28', $meta['distribution_date']);
        self::assertSame(['2026/11/1 予約開始'], $meta['release_notes']);
        self::assertCount(1, $meta['purchase_links']);
        self::assertSame('fanza', $meta['purchase_links'][0]['store']);
        self::assertSame('FANZAで予約', $meta['purchase_links'][0]['label']);
        self::assertSame('https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=1dldss573/', $meta['purchase_links'][0]['url']);
        self::assertSame('2026-11-01', $meta['purchase_links'][0]['available_from']);
        self::assertSame(
            ['出演女優' => '宮上唯依花', '収録時間' => '135分', '配信開始日' => '2026/11/28', '発売日' => '2026/12/3'],
            $meta['source_fields'],
        );
    }

    public function test_work_listing_cards_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('dahlia/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/', type: CrawlType::Listing));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(20, $result->items);
        self::assertSame(self::BASE . '/works/dldss573/', $result->items[0]->url);
        $first = $result->items[0]->meta['movie'];
        self::assertSame('dldss573', $first['external_id']);
        self::assertSame('DLDSS-573', $first['code']);
        self::assertSame('子育てが落ち着いたので今は近所の⚫︎生に私が育てられています。', $first['title']);
        self::assertSame(self::CDN . 'DLDSS-573_2125.jpg', $first['cover_url']);
        self::assertSame('宮上唯依花', $first['performers'][0]['name']);
        self::assertSame(self::CDN . 'DLDSS-573_PR.mp4', $first['metadata']['sample_video_url']);
        self::assertSame('DAHLIA', $first['metadata']['maker']);
        self::assertSame(['2026/11/1 予約開始'], $first['metadata']['release_notes']);
        self::assertSame('予約する', $first['metadata']['purchase_links'][0]['label']);
        self::assertCount(1, $first['metadata']['purchase_links']);

        $second = $result->items[1]->meta['movie'];
        self::assertSame('dldss581', $second['external_id']);
        self::assertSame(['hnext', 'fanza'], array_column($second['metadata']['purchase_links'], 'store'));
        self::assertSame('https://landing.hnext.jp/media/U13JJQF3DP', $second['metadata']['purchase_links'][0]['url']);
        self::assertSame(['2026/11/14 配信開始', '2026/11/1 予約開始'], $second['metadata']['release_notes']);

        self::assertSame('dldss565', $result->items[19]->meta['movie']['external_id']);
        self::assertSame('角奈保', $result->items[19]->meta['movie']['performers'][0]['name']);
    }

    public function test_work_listing_pagination_from_real_capture(): void
    {
        $result = (new Listing($this->clientWithFixture('dahlia/list-work.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/work/', type: CrawlType::Listing));

        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(26, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/work/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_performer_listing_cards_from_real_capture(): void
    {
        $result = (new PerformerListing($this->clientWithFixture('dahlia/actress-list.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/actress/', type: CrawlType::PerformerListing));

        self::assertSame('performer', $result->entityType);
        self::assertCount(48, $result->items);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertSame(self::BASE . '/actress/suzume_mino/', $result->items[0]->url);
        $first = $result->items[0]->meta['performer'];
        self::assertSame('suzume_mino', $first['external_id']);
        self::assertSame('美乃すずめ', $first['name']);
        self::assertSame('美乃すずめ', $first['name_japanese']);
        self::assertSame(['Suzume Mino'], $first['aliases']);
        self::assertSame('Suzume Mino', $first['metadata']['name_romaji']);
        self::assertSame('https://cdn.faleno.net/dahlia/wp-content/uploads/2023/04/mino2.jpg', $first['profile_image_url']);
    }

    public function test_performer_detail_profile_from_real_capture(): void
    {
        $performer = $this->performer();

        self::assertSame('naho_ozawa', $performer['external_id']);
        self::assertSame('小沢菜穂', $performer['name']);
        self::assertSame('小沢菜穂', $performer['name_japanese']);
        self::assertSame(['Naho Ozawa'], $performer['aliases']);
        self::assertSame('https://cdn.faleno.net/dahlia/wp-content/uploads/2024/10/nahoozawa01.jpg', $performer['profile_image_url']);
        self::assertSame('1983/1/26', $performer['birth_date_raw']);
        self::assertSame('157㎝', $performer['height_raw']);
        self::assertSame('B84 W58 H86', $performer['size_raw']);
        self::assertSame(
            ['誕生日' => '1983/1/26', '身長' => '157㎝', 'スリーサイズ' => 'B84 W58 H86', '出身地' => '神奈川県', '趣味' => '読書・旅行', '特技' => 'ピアノ'],
            $performer['raw_profile'],
        );
        self::assertSame('Naho Ozawa', $performer['metadata']['name_romaji']);
        self::assertSame('1983-01-26', $performer['metadata']['birth_date']);
        self::assertSame('神奈川県', $performer['metadata']['birthplace']);
        self::assertSame('読書・旅行', $performer['metadata']['hobby']);
        self::assertSame('ピアノ', $performer['metadata']['skill']);
        self::assertSame(['https://cdn.faleno.net/dahlia/wp-content/uploads/2024/10/nahoozawa01.jpg'], $performer['metadata']['images']);
    }

    public function test_performer_detail_lists_only_her_own_work(): void
    {
        $works = $this->performer()['metadata']['works'];

        self::assertCount(1, $works);
        self::assertSame('dldss581', $works[0]['external_id']);
        self::assertSame('DLDSS-581', $works[0]['code']);
        self::assertSame(self::BASE . '/works/dldss581/', $works[0]['url']);
        self::assertSame('「いくら名湯だからって…。」妻が温泉に行ったきり戻ってきません。 小沢菜穂', $works[0]['title']);
        self::assertSame(self::CDN . 'DLDSS-581_2125.jpg', $works[0]['cover_url']);
        self::assertSame('2026-11-14', $works[0]['date']);
        self::assertSame(140, $works[0]['duration']);
        self::assertSame(self::CDN . 'DLDSS-581_PR.mp4', $works[0]['sample_video_url']);
        self::assertSame(['2026/11/14 配信開始', '2026/11/1 予約開始'], $works[0]['release_notes']);
        self::assertSame(['hnext', 'fanza'], array_column($works[0]['purchase_links'], 'store'));
        self::assertSame(['出演女優' => '小沢菜穂', '配信日' => '2026/11/14', '収録時間' => '140分'], $works[0]['source_fields']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('dahlia');
        self::assertInstanceOf(DahliaCrawler::class, $crawler);
        $expected = [
            '/works/dldss573/' => CrawlType::Detail,
            '/work/' => CrawlType::Listing,
            '/work/page/2/' => CrawlType::Listing,
            '/actress/' => CrawlType::PerformerListing,
            '/actress/naho_ozawa/' => CrawlType::PerformerDetail,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/news/')->matched);
        self::assertSame('dahlia', $crawler->name());
    }

    public function test_code_falls_back_to_the_upper_cased_id(): void
    {
        self::assertSame('DLDSS-573', Site::code('dldss573'));
        self::assertSame('SPECIAL-EDITION', Site::code('special-edition'));
        self::assertNull(Site::workId(self::BASE . '/work/'));
        self::assertNull(Site::performerId(self::BASE . '/actress/'));
    }

    public function test_pages_without_the_expected_markup_are_rejected(): void
    {
        $empty = '<html><body><p>nothing</p></body></html>';

        foreach ([Listing::class, PerformerListing::class, Detail::class, PerformerDetail::class] as $type) {
            try {
                (new $type($this->clientWithHtml($empty)))->execute(new CrawlRequestDto(url: self::BASE . '/actress/x/', type: CrawlType::Detail));
                self::fail($type . ' accepted an empty page');
            } catch (CrawlParseException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @return array<string, mixed> */
    private function performer(): array
    {
        return (new PerformerDetail($this->clientWithFixture('dahlia/actress-naho_ozawa.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/actress/naho_ozawa/', type: CrawlType::PerformerDetail))
            ->meta['performer'];
    }
}
