<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Image audit of the paysite / WordPress maker adapters: every real fixture is crawled and the number of image slots
 * it fills is pinned, so a lost lazy image, thumbnail pair, poster or performer photo fails here.
 */
final class ImageCompletenessAuditTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string, array{cover: int, screenshots: int, thumbnails: int, photos: int}}>
     */
    public static function fixtures(): iterable
    {
        $detail = static fn(int $screenshots, int $thumbnails): array => ['cover' => 1, 'screenshots' => $screenshots, 'thumbnails' => $thumbnails, 'photos' => 0];
        $list = static fn(int $items): array => ['cover' => $items, 'screenshots' => 0, 'thumbnails' => 0, 'photos' => 0];
        $people = static fn(int $items): array => ['cover' => 0, 'screenshots' => 0, 'thumbnails' => 0, 'photos' => $items];

        yield 'onepondo detail' => ['onepondo', 'detail-060426_001.json', $detail(0, 0)];
        yield 'onepondo list' => ['onepondo', 'list-newest-0.json', $list(50)];
        yield 'tenmusume detail' => ['10musume', 'detail-110326_01.json', $detail(0, 0)];
        yield 'tenmusume list' => ['10musume', 'list-newest-0.json', $list(50)];
        yield 'pacopacomama detail' => ['pacopacomama', 'detail-110326_100.json', $detail(3, 0)];
        yield 'pacopacomama list' => ['pacopacomama', 'list-newest-0.json', $list(2)];
        yield 'muramura detail' => ['muramura', 'detail-103126_1273.json', $detail(0, 0)];
        yield 'muramura list' => ['muramura', 'list-newest-0.json', $list(1)];
        yield 'caribbeancom detail' => ['caribbeancom', 'detail.html', $detail(40, 40)];
        yield 'caribbeancom list' => ['caribbeancom', 'listing.html', $list(20)];
        yield 'caribbeancompr detail' => ['caribbeancompr', 'detail.html', $detail(31, 31)];
        yield 'caribbeancompr detail ja' => ['caribbeancompr', 'detail-ja.html', $detail(31, 31)];
        yield 'caribbeancompr list' => ['caribbeancompr', 'listing.html', $list(40)];
        yield 'heyzo detail' => ['heyzo', 'detail.html', $detail(3, 3)];
        yield 'heyzo list' => ['heyzo', 'listing.html', $list(30)];
        yield 'tokyohot detail' => ['tokyohot', 'detail.html', $detail(30, 30)];
        yield 'tokyohot detail tags' => ['tokyohot', 'detail-tags.html', $detail(20, 20)];
        yield 'tokyohot list' => ['tokyohot', 'listing.html', $list(78)];
        yield 'tokyohot products' => ['tokyohot', 'listing-products.html', $list(50)];
        yield 'fc2 detail' => ['fc2', 'detail.html', $detail(5, 5)];
        yield 'fc2 list' => ['fc2', 'listing.html', $list(45)];
        yield 'duga detail' => ['duga', 'detail.html', $detail(10, 10)];
        yield 'duga ranking' => ['duga', 'listing-ranking.html', $list(100)];
        yield 'aisex performer detail' => ['aisex', 'detail-aiuti-siori.html', ['cover' => 0, 'screenshots' => 0, 'thumbnails' => 0, 'photos' => 1]];
        yield 'aisex performer list' => ['aisex', 'listing-page1.html', $people(36)];
        yield 'falenogroup detail fthtd' => ['falenogroup', 'detail-fthtd-243.html', $detail(15, 15)];
        yield 'falenogroup detail ftkd' => ['falenogroup', 'detail-ftkd-046.html', $detail(15, 15)];
        yield 'falenogroup detail noskn' => ['falenogroup', 'detail-noskn-106.html', $detail(17, 17)];
        yield 'falenogroup list' => ['falenogroup', 'list-work.html', $list(28)];
        yield 'falenogroup maker' => ['falenogroup', 'maker-noskn.html', $list(12)];
        yield 'dahlia detail' => ['dahlia', 'detail-DLDSS-573.html', $detail(10, 10)];
        yield 'dahlia list' => ['dahlia', 'list-work.html', $list(20)];
        yield 'dahlia performer list' => ['dahlia', 'actress-list.html', $people(48)];
        yield 'dahlia performer detail' => ['dahlia', 'actress-naho_ozawa.html', ['cover' => 0, 'screenshots' => 0, 'thumbnails' => 0, 'photos' => 1]];
        yield 'materiall detail' => ['materiall', 'detail-mtafb-008.html', $detail(0, 0)];
        yield 'materiall list' => ['materiall', 'list-work.html', $list(12)];
        yield 'plum detail' => ['plum', 'detail-puw070.html', $detail(20, 20)];
        yield 'plum home' => ['plum', 'listing-home.html', $list(26)];
        yield 'plum series' => ['plum', 'listing-series-puw.html', $list(20)];
        yield 'indies detail' => ['indies', 'detail-dsdp049.html', $detail(20, 20)];
        yield 'indies list' => ['indies', 'list-genre-anal.html', $list(40)];
        yield 'indies performer list' => ['indies', 'actpage.html', $people(9)];
        yield 'indies performer detail' => ['indies', 'actress-kana_yura.html', ['cover' => 0, 'screenshots' => 0, 'thumbnails' => 0, 'photos' => 1]];
        yield 'rocket detail' => ['rocket', 'detail-rctd-683.html', $detail(10, 10)];
        yield 'rocket works' => ['rocket', 'works.html', $list(24)];
        yield 'deeps detail' => ['deeps', 'detail-gdjp-012.html', $detail(10, 0)];
        yield 'deeps list' => ['deeps', 'list.html', $list(34)];
        yield 'next11 detail' => ['next11', 'detail-KANZ-354.html', $detail(6, 6)];
        yield 'next11 list' => ['next11', 'list-label-4343.html', $list(30)];
        yield 'dandy detail' => ['dandy', 'detail-dandy-880.html', $detail(2, 2)];
        yield 'dandy list' => ['dandy', 'works-dandy.html', $list(20)];
    }

    /**
     * @param  array{cover: int, screenshots: int, thumbnails: int, photos: int}  $expected
     */
    #[DataProvider('fixtures')]
    public function test_every_image_slot_the_fixture_offers_is_filled(string $slug, string $name, array $expected): void
    {
        $result = $this->crawl($slug, $name);

        $covers = $screenshots = $thumbnails = $photos = 0;
        if ($result instanceof CrawlListResultDto) {
            foreach ($result->items as $item) {
                $entity = $item->meta[$item->entityType];
                $covers += ($entity['cover_url'] ?? null) !== null ? 1 : 0;
                $photos += ($entity['profile_image_url'] ?? null) !== null ? 1 : 0;
            }
        } else {
            $entity = $result->meta[$result->entityType];
            $covers = ($entity['cover_url'] ?? null) !== null ? 1 : 0;
            $photos = ($entity['profile_image_url'] ?? null) !== null ? 1 : 0;
            $screenshots = count($entity['screenshots'] ?? []);
            $thumbnails = count(array_filter($entity['screenshots'] ?? [], static fn(array $shot): bool => ($shot['thumbnail_url'] ?? null) !== null));
        }

        self::assertSame($expected, ['cover' => $covers, 'screenshots' => $screenshots, 'thumbnails' => $thumbnails, 'photos' => $photos]);
    }

    public function test_heyzo_keeps_the_thumbnails_of_the_pictures_a_guest_cannot_open(): void
    {
        $metadata = $this->movie('heyzo', 'detail.html')['metadata'];

        self::assertCount(17, $metadata['locked_gallery_thumbnails']);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/gallery/thumbnail_004.jpg', $metadata['locked_gallery_thumbnails'][0]);
        self::assertSame('https://en.heyzo.com/contents/3000/3927/gallery/thumbnail_020.jpg', $metadata['locked_gallery_thumbnails'][16]);
        self::assertCount(99, $metadata['special_gallery_thumbnails']);
        self::assertSame('https://en.heyzo.com/contents/3000/albums/3927/small/heyzo_3927_001_s.jpg', $metadata['special_gallery_thumbnails'][0]);
        self::assertSame('https://en.heyzo.com/contents/3000/albums/3927/small/heyzo_3927_099_s.jpg', $metadata['special_gallery_thumbnails'][98]);
    }

    public function test_tokyohot_returns_the_jacket_and_package_images_and_the_cover_of_a_product_without_a_sample(): void
    {
        $metadata = $this->movie('tokyohot', 'detail.html')['metadata'];

        self::assertSame('https://my.cdn.tokyo-hot.com/media/n1700/jacket/n1700_cv.jpg', $metadata['jacket_url']);
        self::assertSame([
            'https://my.cdn.tokyo-hot.com/media/n1700/package/_v.jpg',
            'https://my.cdn.tokyo-hot.com/media/n1700/package/_vb.jpg',
        ], $metadata['package_images']);

        $noSample = $this->movie('tokyohot', 'detail-tags.html');
        self::assertSame('https://my.cdn.tokyo-hot.com/media/adl_301/list_image/20261014211642/820x462_default.jpg', $noSample['cover_url']);
        self::assertArrayNotHasKey('jacket_url', $noSample['metadata']);
    }

    public function test_duga_covers_with_the_full_size_jacket_and_keeps_the_thumbnail_and_sample_poster(): void
    {
        $movie = $this->movie('duga', 'detail.html');

        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/jacket.jpg', $movie['cover_url']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/jacket_240.jpg', $movie['metadata']['thumbnail_url']);
        self::assertSame('https://pic.duga.jp/unsecure/vrpandemic/0023/noauth/flvcap.jpg', $movie['metadata']['sample_video_poster_url']);
    }

    public function test_fc2_samples_carry_the_original_storage_file(): void
    {
        $shots = $this->movie('fc2', 'detail.html')['screenshots'];

        self::assertCount(5, $shots);
        foreach ($shots as $shot) {
            self::assertStringStartsWith('https://storage202000.contents.fc2.com/file/399/39896198/', $shot['metadata']['original_url']);
            self::assertStringEndsWith(substr($shot['url'], (int) strrpos($shot['url'], '/')), $shot['metadata']['original_url']);
        }
    }

    public function test_rocket_images_are_the_origin_files_not_the_jetpack_resized_copies(): void
    {
        $movie = $this->movie('rocket', 'detail-rctd-683.html');

        self::assertSame('https://rocket-inc.net/wp-content/uploads/2025/08/rctd-683-jacket.jpg', $movie['cover_url']);
        self::assertSame('https://rocket-inc.net/wp-content/uploads/2025/08/rctd-683-sample-1.jpg', $movie['screenshots'][0]['url']);
        self::assertSame('https://i0.wp.com/rocket-inc.net/wp-content/uploads/2025/08/rctd-683-sample-1.jpg?resize=480%2C360&ssl=1', $movie['screenshots'][0]['thumbnail_url']);
        foreach ($movie['screenshots'] as $shot) {
            self::assertStringStartsWith('https://rocket-inc.net/wp-content/uploads/', $shot['url']);
        }

        foreach ($this->crawl('rocket', 'works.html')->items as $item) {
            self::assertStringStartsWith('https://rocket-inc.net/wp-content/uploads/', $item->meta['movie']['cover_url']);
        }
    }

    public function test_aisex_listing_and_related_cards_carry_the_photo_but_not_the_placeholder(): void
    {
        $items = $this->crawl('aisex', 'listing-page1.html')->items;

        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/ai.jpg', $items[1]->meta['performer']['profile_image_url']);
        self::assertNull($items[0]->meta['performer']['profile_image_url']);

        $related = $this->crawl('aisex', 'detail-aiuti-siori.html')->meta['performer']['metadata']['related_performers'];
        self::assertCount(6, $related);
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/nanami_thina.jpg', $related[0]['profile_image_url']);
        self::assertSame(6, count(array_filter(array_column($related, 'profile_image_url'))));
    }

    public function test_falenogroup_maker_page_logos_are_not_recompressed(): void
    {
        foreach ($this->crawl('falenogroup', 'maker-noskn.html')->items as $item) {
            self::assertSame('https://cdn.faleno.net/group/wp-content/uploads/2022/05/noskins.jpg', $item->meta['movie']['metadata']['maker_logo_url']);
        }
    }

    public function test_onepondo_family_covers_come_with_the_thumbnail_variants(): void
    {
        $onepondo = $this->movie('onepondo', 'detail-060426_001.json');
        self::assertSame('https://www.1pondo.tv/moviepages/060426_001/images/str.jpg', $onepondo['cover_url']);
        self::assertSame('https://www.1pondo.tv/moviepages/060426_001/images/thum_b.jpg', $onepondo['metadata']['thumbnail_url']);

        $paco = $this->movie('pacopacomama', 'detail-110326_100.json');
        self::assertSame('https://www.pacopacomama.com/moviepages/110326_100/images/l_hd.jpg', $paco['cover_url']);
        self::assertSame('https://www.pacopacomama.com/moviepages/110326_100/images/l_thum.jpg', $paco['metadata']['thumbnail_url']);
        // 20 gallery rows: 3 public samples, 17 member-only that the site marks "Protected".
        self::assertCount(3, $paco['screenshots']);
    }

    /** @return array<string, mixed> */
    private function movie(string $slug, string $name): array
    {
        $result = $this->crawl($slug, $name);
        self::assertInstanceOf(CrawlItemResultDto::class, $result);

        return $result->meta[$result->entityType];
    }

    private function crawl(string $slug, string $name): CrawlItemResultDto|CrawlListResultDto
    {
        $manifest = (new FileAdapterManifestRegistry())->get($slug);
        self::assertNotNull($manifest);
        foreach ($manifest->fixtureSamples as $sample) {
            if ($sample->name !== $name) {
                continue;
            }

            $relative = ManifestFixtureCatalog::fixtureRelativePath($slug, $name);
            ManifestFixtureCatalog::registerFollowUps($slug, $sample);
            FixtureResponder::for('GET', $sample->url)->file($relative);
            ManifestFixtureCatalog::registerSupportingResponses($slug);
            if ($slug === 'pacopacomama') {
                FixtureResponder::for('GET', 'https://www.pacopacomama.com/dyn/dla/json/movie_gallery/110326_100.json')->file('pacopacomama/gallery-110326_100.json');
            }

            return CrawlerX::url($sample->url)->crawl();
        }

        self::fail('No manifest sample ' . $slug . '/' . $name);
    }
}
