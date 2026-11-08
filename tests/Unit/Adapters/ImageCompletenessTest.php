<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters;

use JOOservices\CrawlerX\Adapters\Aisex\Types\PerformerDetail as AisexPerformerDetail;
use JOOservices\CrawlerX\Adapters\Aisex\Types\PerformerListing as AisexPerformerListing;
use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerDetail as AvfanProfilesPerformerDetail;
use JOOservices\CrawlerX\Adapters\AvfanProfiles\Types\PerformerListing as AvfanProfilesPerformerListing;
use JOOservices\CrawlerX\Adapters\Avfan\Types\Detail as AvfanDetail;
use JOOservices\CrawlerX\Adapters\Avfan\Types\Listing as AvfanListing;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerDetail as AvjohoPerformerDetail;
use JOOservices\CrawlerX\Adapters\Avjoho\Types\PerformerListing as AvjohoPerformerListing;
use JOOservices\CrawlerX\Adapters\Duga\Types\Detail as DugaDetail;
use JOOservices\CrawlerX\Adapters\Duga\Types\Listing as DugaListing;
use JOOservices\CrawlerX\Adapters\Fc2\Types\Detail as Fc2Detail;
use JOOservices\CrawlerX\Adapters\Fc2\Types\Listing as Fc2Listing;
use JOOservices\CrawlerX\Adapters\FfJav\Types\Detail as FfJavDetail;
use JOOservices\CrawlerX\Adapters\FfJav\Types\Listing as FfJavListing;
use JOOservices\CrawlerX\Adapters\Jable\Types\Detail as JableDetail;
use JOOservices\CrawlerX\Adapters\Jable\Types\Listing as JableListing;
use JOOservices\CrawlerX\Adapters\Jable\Types\PerformerDetail as JablePerformerDetail;
use JOOservices\CrawlerX\Adapters\Jable\Types\PerformerListing as JablePerformerListing;
use JOOservices\CrawlerX\Adapters\Javbtc\Types\Detail as JavbtcDetail;
use JOOservices\CrawlerX\Adapters\Javbtc\Types\Listing as JavbtcListing;
use JOOservices\CrawlerX\Adapters\Missav\Types\Detail as MissavDetail;
use JOOservices\CrawlerX\Adapters\Missav\Types\Listing as MissavListing;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Detail as OnejavDetail;
use JOOservices\CrawlerX\Adapters\Onejav\Types\Listing as OnejavListing;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Detail as OneFourOneJavDetail;
use JOOservices\CrawlerX\Adapters\OneFourOneJav\Types\Listing as OneFourOneJavListing;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\TestCase;

/**
 * Image completeness of the torrent/streaming indexes and the remaining adapters (JOOXCR-423): every image the page
 * shows for a work or a person reaches the DTO, from the real fixtures, so a count cannot regress silently.
 */
class ImageCompletenessTest extends TestCase
{
    // --- Missav -----------------------------------------------------------------------------------------------------

    public function test_missav_listing_resolves_every_lazy_cover_and_keeps_the_thumbnail(): void
    {
        $movies = $this->listing(MissavListing::class, 'missav/listing-new.html', 'https://missav.ws/en/new', CrawlType::Listing);

        self::assertCount(12, $movies);
        foreach ($movies as $movie) {
            // Half the cards are lazy (src is an inline placeholder, data-src the real image).
            self::assertMatchesRegularExpression('#^https://fourhoi\.com/[^/]+/cover-n\.jpg$#', $movie['cover_url']);
            self::assertMatchesRegularExpression('#^https://fourhoi\.com/[^/]+/cover-t\.jpg$#', $movie['metadata']['thumbnail_url']);
        }
        self::assertSame('https://fourhoi.com/mium-1446/cover-n.jpg', $movies[6]['cover_url']);
    }

    public function test_missav_detail_has_the_full_size_cover_and_no_related_covers(): void
    {
        $movie = $this->detail(MissavDetail::class, 'missav/detail-fns-247.html', 'https://missav.ws/en/fns-247');

        self::assertSame('https://fourhoi.com/fns-247/cover-n.jpg', $movie['cover_url']);
        self::assertSame([], $movie['screenshots']);
    }

    // --- Jable ------------------------------------------------------------------------------------------------------

    public function test_jable_listing_covers_are_complete(): void
    {
        $movies = $this->listing(JableListing::class, 'jable/listing-new-release.html', 'https://en.jable.tv/new-release/', CrawlType::Listing);

        self::assertCount(24, $movies);
        self::assertCount(24, array_filter(array_column($movies, 'cover_url')));
        self::assertSame('https://assets-cdn.jable.tv/contents/videos_screenshots/61000/61845/320x180/1.jpg', $movies[0]['cover_url']);
    }

    public function test_jable_detail_has_the_poster_and_the_performer_photo(): void
    {
        $withoutPhoto = $this->detail(JableDetail::class, 'jable/detail-jur-832.html', 'https://en.jable.tv/videos/jur-832/');
        self::assertSame('https://assets-cdn.jable.tv/contents/videos_screenshots/61000/61844/preview.jpg', $withoutPhoto['cover_url']);
        self::assertSame('https://assets-cdn.jable.tv/contents/videos_screenshots/61000/61844/preview.jpg', $withoutPhoto['metadata']['stream']['poster_url']);
        self::assertNull($withoutPhoto['performers'][0]['profile_image_url'] ?? null);

        $withPhoto = $this->detail(JableDetail::class, 'jable/detail-fjin-091.html', 'https://en.jable.tv/videos/fjin-091/');
        self::assertSame('https://assets-cdn.jable.tv/contents/models/76/s1_julia.jpg', $withPhoto['performers'][0]['profile_image_url']);
    }

    public function test_jable_performer_listing_carries_the_photo_of_the_models_that_have_one(): void
    {
        $result = $this->crawl(JablePerformerListing::class, 'jable/models-listing.html', 'https://en.jable.tv/models/', CrawlType::PerformerListing);
        $photos = array_filter(array_map(static fn($item): ?string => $item->meta['performer']['profile_image_url'] ?? null, $result->items));

        self::assertCount(20, $result->items);
        self::assertSame([
            4 => 'https://assets-cdn.jable.tv/contents/models/32/s1_mikami_yua.jpg',
            9 => 'https://assets-cdn.jable.tv/contents/models/18/s1_hasimoto_arina.jpg',
        ], $photos);
    }

    public function test_jable_performer_detail_never_uses_a_video_screenshot_as_the_photo(): void
    {
        $item = $this->crawl(JablePerformerDetail::class, 'jable/performer-detail-bfaca44240620be2f3092c294fb22fbe.html', 'https://en.jable.tv/models/bfaca44240620be2f3092c294fb22fbe/', CrawlType::PerformerDetail);

        self::assertNull($item->meta['performer']['profile_image_url'] ?? null);
        self::assertSame(['videos' => '1 videos'], $item->meta['performer']['raw_profile']);
    }

    // --- Javbtc -----------------------------------------------------------------------------------------------------

    public function test_javbtc_listing_gives_the_original_cover_next_to_the_480_thumbnail(): void
    {
        $movies = $this->listing(JavbtcListing::class, 'javbtc/listing-page-1.html', 'https://javbtc.com/r18/', CrawlType::Listing);

        self::assertCount(48, $movies);
        foreach ($movies as $movie) {
            self::assertStringEndsNotWith('-480.jpg', $movie['cover_url']);
            self::assertSame(str_replace('.jpg', '-480.jpg', $movie['cover_url']), $movie['metadata']['thumbnail_url']);
        }
        self::assertSame('https://g.uuu.cam/movie/jav.download.video/himari.kinoshita/video8499/1.jpg', $movies[0]['cover_url']);
    }

    public function test_javbtc_detail_has_the_poster(): void
    {
        $movie = $this->detail(JavbtcDetail::class, 'javbtc/detail-sample-1.html', 'https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity');

        self::assertSame('https://g.uuu.cam/movie/avgle/yua.mikami/video8321/1.jpg', $movie['cover_url']);
    }

    // --- Onejav family ----------------------------------------------------------------------------------------------

    public function test_onejav_listing_covers_are_complete_and_not_recompressed(): void
    {
        $movies = $this->listing(OnejavListing::class, 'onejav/listing-page-1.html', 'https://onejav.com/', CrawlType::Listing);

        self::assertCount(10, $movies);
        self::assertCount(10, array_filter(array_column($movies, 'cover_url')));
        self::assertSame('https://cdn.faleno.net/top/wp-content/uploads/2026/08/FNS-256_1200.jpg', $movies[0]['cover_url']);
        foreach ($movies as $movie) {
            self::assertStringNotContainsString('output-quality', $movie['cover_url']);
        }
    }

    public function test_onejav_detail_has_one_cover_and_no_related_covers(): void
    {
        $movie = $this->detail(OnejavDetail::class, 'onejav/detail-sample-1.html', 'https://onejav.com/torrent/ymds282');

        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/ymds282/ymds282pl.jpg', $movie['cover_url']);
        self::assertSame([], $movie['screenshots']);
    }

    public function test_141jav_listing_and_detail_covers_are_complete(): void
    {
        $movies = $this->listing(OneFourOneJavListing::class, '141jav/listing-page-1.html', 'https://www.141jav.com/', CrawlType::Listing);
        self::assertCount(10, $movies);
        self::assertCount(10, array_filter(array_column($movies, 'cover_url')));

        $movie = $this->detail(OneFourOneJavDetail::class, '141jav/detail-sample-1.html', 'https://www.141jav.com/torrent/JUQ521');
        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/juq521/juq521pl.jpg', $movie['cover_url']);
    }

    public function test_ffjav_listing_and_detail_covers_are_complete(): void
    {
        $movies = $this->listing(FfJavListing::class, 'ffjav/listing-page-1.html', 'https://ffjav.com/', CrawlType::Listing);
        self::assertCount(10, $movies);
        self::assertCount(10, array_filter(array_column($movies, 'cover_url')));

        $movie = $this->detail(FfJavDetail::class, 'ffjav/detail-sample-1.html', 'https://ffjav.com/torrent/mida-768');
        self::assertSame('https://pics.dmm.co.jp/mono/movie/adult/mida768/mida768pl.jpg', $movie['cover_url']);
    }

    // --- Avfan ------------------------------------------------------------------------------------------------------

    public function test_avfan_listing_cards_carry_their_cover(): void
    {
        $movies = $this->listing(AvfanListing::class, 'Avfan/listing-placeholder.html', 'https://avfan.com/en', CrawlType::Listing);

        self::assertCount(42, $movies);
        self::assertCount(42, array_filter(array_column($movies, 'cover_url')));
        self::assertSame('https://c1.avfan.com/covers/0u/0u4eTb.jpg', $movies[0]['cover_url']);
    }

    public function test_avfan_detail_has_the_cover_and_every_sample_with_its_thumbnail(): void
    {
        $movie = $this->detail(AvfanDetail::class, 'Avfan/detail-ewTlM3Fj.html', 'https://avfan.com/en/movies/ewTlM3Fj');

        self::assertSame('https://c1.avfan.com/covers/tq/TqCcgV.jpg', $movie['cover_url']);
        self::assertCount(5, $movie['screenshots']);
        self::assertSame('https://c1.avfan.com/samples/4y/4yN8CO/l_5.jpg', $movie['screenshots'][4]['url']);
        self::assertSame('https://c1.avfan.com/samples/4y/4yN8CO/s_5.jpg', $movie['screenshots'][4]['thumbnail_url']);
    }

    // --- Performer-only adapters ------------------------------------------------------------------------------------

    public function test_aisex_performer_listing_skips_the_no_image_stand_in(): void
    {
        $result = $this->crawl(AisexPerformerListing::class, 'Aisex/listing-page1.html', 'https://www.aisex.jp/actress/', CrawlType::PerformerListing);
        $photos = array_filter(array_map(static fn($item): ?string => $item->meta['performer']['profile_image_url'] ?? null, $result->items));

        self::assertCount(50, $result->items);
        self::assertCount(36, $photos);
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/ai.jpg', $photos[1]);
        foreach ($photos as $photo) {
            self::assertStringNotContainsString('no-image', $photo);
        }
    }

    public function test_aisex_performer_detail_has_the_profile_photo(): void
    {
        $item = $this->crawl(AisexPerformerDetail::class, 'Aisex/detail-aiuti-siori.html', 'https://www.aisex.jp/actress/1004850/', CrawlType::PerformerDetail);

        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/aiuti_siori.jpg', $item->meta['performer']['profile_image_url']);
    }

    public function test_avjoho_performer_listing_carries_every_card_image(): void
    {
        $result = $this->crawl(AvjohoPerformerListing::class, 'avjoho/listing-page1.html', 'https://db.avjoho.com/category/x/', CrawlType::PerformerListing);
        $photos = array_map(static fn($item): ?string => $item->meta['performer']['profile_image_url'] ?? null, $result->items);

        self::assertCount(20, $result->items);
        self::assertCount(20, array_filter($photos));
        self::assertSame('https://pics.dmm.co.jp/digital/video/snos00299/snos00299pl.jpg', $photos[0]);
        self::assertSame('https://pics.dmm.co.jp/digital/video/snos00299/snos00299ps.jpg', $result->items[0]->meta['performer']['metadata']['thumbnail_url'] ?? null);
    }

    public function test_avjoho_performer_detail_has_the_photo_and_the_work_covers(): void
    {
        $item = $this->crawl(AvjohoPerformerDetail::class, 'avjoho/detail-ichise-airi.html', 'https://db.avjoho.com/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/', CrawlType::PerformerDetail);

        self::assertSame('https://pics.dmm.co.jp/digital/video/pred00822/pred00822pl.jpg', $item->meta['performer']['profile_image_url']);
        self::assertCount(2, $item->meta['performer']['metadata']['notable_works']);
    }

    public function test_avfan_profiles_performer_listing_carries_every_card_photo(): void
    {
        $result = $this->crawl(AvfanProfilesPerformerListing::class, 'AvfanProfiles/listing-cup-a.html', 'https://av-fan.tokyo/actress/cup/A.html', CrawlType::PerformerListing);
        $photos = array_map(static fn($item): ?string => $item->meta['performer']['profile_image_url'] ?? null, $result->items);

        self::assertCount(104, $result->items);
        self::assertCount(104, array_filter($photos));
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/nagase_yui2.jpg', $photos[0]);
    }

    public function test_avfan_profiles_performer_detail_has_the_photo(): void
    {
        $item = $this->crawl(AvfanProfilesPerformerDetail::class, 'AvfanProfiles/detail-arisu-mai.txt', 'https://av-fan.tokyo/actress/%E6%9C%89%E6%A0%96%E8%88%9E%E8%A1%A3.html', CrawlType::PerformerDetail);

        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/arisu_mai.jpg', $item->meta['performer']['profile_image_url']);
    }

    // --- Duga / Fc2 -------------------------------------------------------------------------------------------------

    public function test_duga_listing_gives_the_full_jacket_next_to_the_thumbnail(): void
    {
        $movies = $this->listing(DugaListing::class, 'duga/listing-ranking.html', 'https://duga.jp/ranking/', CrawlType::Listing);

        self::assertCount(100, $movies);
        self::assertCount(100, array_filter(array_column($movies, 'cover_url')));
        self::assertSame('https://pic.duga.jp/unsecure/flower/0835/noauth/jacket.jpg', $movies[0]['cover_url']);
        self::assertSame('https://pic.duga.jp/unsecure/flower/0835/noauth/jacket_120.jpg', $movies[0]['metadata']['thumbnail_url']);
        // Square products have no jacket.jpg; their own image stays the cover.
        self::assertSame('https://pic.duga.jp/unsecure/fellatiohunter/0238/noauth/180x180.jpg', $movies[14]['cover_url']);
        self::assertCount(90, array_filter(array_column(array_column($movies, 'metadata'), 'thumbnail_url')));
    }

    public function test_duga_detail_has_the_full_jacket_the_thumbnail_and_every_capture(): void
    {
        $movie = $this->detail(DugaDetail::class, 'duga/detail.html', 'https://duga.jp/ppv/vrpandemic-0023/');

        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/jacket.jpg', $movie['cover_url']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/jacket_240.jpg', $movie['metadata']['thumbnail_url']);
        self::assertCount(10, $movie['screenshots']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/cap/0010.jpg', $movie['screenshots'][9]['url']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/scap/0010.jpg', $movie['screenshots'][9]['thumbnail_url']);
    }

    public function test_fc2_listing_gives_the_original_cover_next_to_the_thumbnail(): void
    {
        $movies = $this->listing(Fc2Listing::class, 'fc2/listing.html', 'https://adult.contents.fc2.com/', CrawlType::Listing);

        self::assertCount(45, $movies);
        foreach ($movies as $movie) {
            self::assertMatchesRegularExpression('#^https://storage\d+\.contents\.fc2\.com/file/#', $movie['cover_url']);
            self::assertMatchesRegularExpression('#^https://contents-thumbnail\d*\.fc2\.com/w360/storage#', $movie['metadata']['thumbnail_url']);
        }
    }

    public function test_fc2_detail_has_the_cover_and_every_sample_with_its_thumbnail(): void
    {
        $movie = $this->detail(Fc2Detail::class, 'fc2/detail.html', 'https://adult.contents.fc2.com/article/4968399/');

        self::assertSame('https://storage202000.contents.fc2.com/file/399/39896198/1787909480.39.png', $movie['cover_url']);
        self::assertCount(5, $movie['screenshots']);
        foreach ($movie['screenshots'] as $screenshot) {
            self::assertStringContainsString('/w1280/', $screenshot['url']);
            self::assertStringContainsString('/w480/', $screenshot['thumbnail_url']);
        }
    }

    // --- helpers ----------------------------------------------------------------------------------------------------

    /**
     * @param  class-string  $type
     * @return list<array<string, mixed>>
     */
    private function listing(string $type, string $fixture, string $url, CrawlType $crawlType): array
    {
        return array_map(
            static fn($item): array => $item->meta['movie'],
            $this->crawl($type, $fixture, $url, $crawlType)->items,
        );
    }

    /**
     * @param  class-string  $type
     * @return array<string, mixed>
     */
    private function detail(string $type, string $fixture, string $url): array
    {
        return $this->crawl($type, $fixture, $url, CrawlType::Detail)->meta['movie'];
    }

    /** @param  class-string  $type */
    private function crawl(string $type, string $fixture, string $url, CrawlType $crawlType): mixed
    {
        $client = $this->clientWithFixture($fixture);
        self::assertInstanceOf(CrawlHttpClient::class, $client);

        return (new $type($client))->execute(new CrawlRequestDto(url: $url, type: $crawlType));
    }
}
