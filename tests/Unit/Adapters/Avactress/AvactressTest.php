<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avactress;

use JOOservices\CrawlerX\Adapters\Avactress\AvactressCrawler;
use JOOservices\CrawlerX\Adapters\Avactress\Site;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AvactressTest extends TestCase
{
    private const BASE = 'https://av-actress.com';

    private const HATANO = self::BASE . '/26225/%E6%B3%A2%E5%A4%9A%E9%87%8E%E7%B5%90%E8%A1%A3';

    private const KAWAKITA = self::BASE . '/1044864/%E6%B2%B3%E5%8C%97%E5%BD%A9%E8%8A%B1%EF%BC%88%E6%B2%B3%E5%8C%97%E5%BD%A9%E4%BC%8A%EF%BC%89';

    private const CDN = 'https://pics.dmm.co.jp/digital/video/';

    /** @return array<string, mixed> */
    private function performer(string $fixture, string $url): array
    {
        return (new PerformerDetail($this->clientWithFixture('avactress/' . $fixture)))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::PerformerDetail))
            ->meta['performer'];
    }

    private function listing(string $fixture, string $url): CrawlListResultDto
    {
        return (new PerformerListing($this->clientWithFixture('avactress/' . $fixture)))
            ->execute(new CrawlRequestDto(url: $url, type: CrawlType::PerformerListing));
    }

    public function test_profile_fields_from_real_capture(): void
    {
        $performer = $this->performer('performer-26225.html', self::HATANO);

        self::assertSame('26225', $performer['external_id']);
        self::assertSame(self::HATANO, $performer['url']);
        self::assertSame('波多野結衣', $performer['name']);
        self::assertSame('波多野結衣', $performer['name_japanese']);
        self::assertSame([], $performer['aliases']);
        self::assertNull($performer['profile_image_url']);
        self::assertSame('1988-05-24', $performer['birth_date_raw']);
        self::assertSame('163cm', $performer['height_raw']);
        self::assertSame('B88(E) W59 H85', $performer['size_raw']);
        self::assertSame(
            ['バスト' => '88cm (Eカップ)', 'ウエスト' => '59cm', 'ヒップ' => '85cm', '身長' => '163cm', '誕生日' => '1988-05-24（38歳）', '血液型' => 'A', '出身' => '京都府', '趣味' => 'ゲーム、アニメ'],
            $performer['raw_profile'],
        );
    }

    public function test_profile_metadata_from_real_capture(): void
    {
        $meta = $this->performer('performer-26225.html', self::HATANO)['metadata'];

        self::assertSame('波多野結衣', $meta['display_name']);
        self::assertSame('はたのゆい', $meta['name_kana']);
        self::assertSame('女優', $meta['job_title']);
        self::assertSame('波多野結衣のプロフィール・出演作品一覧。スリーサイズ: B88(E) W59 H85。誕生日: 1988-05-24。出身: 京都府。', $meta['description']);
        self::assertSame('https://www.dmm.co.jp/digital/videoa/-/list/=/article=actress/id=26225/?affiliate_id=ahiruchan-004', $meta['fanza_url']);
        self::assertSame(88, $meta['bust_cm']);
        self::assertSame('E', $meta['cup']);
        self::assertSame(59, $meta['waist_cm']);
        self::assertSame(85, $meta['hip_cm']);
        self::assertSame(163, $meta['height_cm']);
        self::assertSame('1988-05-24', $meta['birth_date']);
        self::assertSame(38, $meta['age']);
        self::assertSame('A', $meta['blood_type']);
        self::assertSame('京都府', $meta['birthplace']);
        self::assertSame('ゲーム、アニメ', $meta['hobby']);
        self::assertSame(['ゲーム', 'アニメ'], $meta['hobbies']);
        self::assertSame(
            ['バスト' => '88cm (Eカップ)', 'ウエスト' => '59cm', 'ヒップ' => '85cm', '身長' => '163cm', '誕生日' => '1988-05-24（38歳）', '血液型' => 'A', '出身' => '京都府', '趣味' => 'ゲーム、アニメ'],
            $meta['source_fields'],
        );
    }

    public function test_profile_with_a_bracketed_alias_and_no_birthday(): void
    {
        $performer = $this->performer('performer-1044864.html', self::KAWAKITA);

        self::assertSame('1044864', $performer['external_id']);
        self::assertSame('河北彩花', $performer['name']);
        self::assertSame(['河北彩伽'], $performer['aliases']);
        self::assertSame('河北彩花（河北彩伽）', $performer['metadata']['display_name']);
        self::assertSame('かわきたさいか', $performer['metadata']['name_kana']);
        self::assertSame('B87(E) W57 H86', $performer['size_raw']);
        self::assertSame('169cm', $performer['height_raw']);
        self::assertSame(169, $performer['metadata']['height_cm']);
        self::assertSame('映画鑑賞', $performer['metadata']['hobby']);
        self::assertSame(['映画鑑賞'], $performer['metadata']['hobbies']);
        self::assertNull($performer['birth_date_raw']);
        self::assertArrayNotHasKey('birth_date', $performer['metadata']);
        self::assertArrayNotHasKey('blood_type', $performer['metadata']);
        self::assertArrayNotHasKey('birthplace', $performer['metadata']);
    }

    public function test_profile_works_pagination_and_filters(): void
    {
        $meta = $this->performer('performer-26225.html', self::HATANO)['metadata'];

        self::assertSame(1, $meta['works_page']);
        self::assertSame(2, $meta['works_last_page']);
        self::assertSame(self::HATANO . '?page=2', $meta['works_next_url']);
        self::assertSame(['all', '2d', 'vr'], array_column($meta['works_filters'], 'key'));
        self::assertSame(['すべて', '2D', 'VR'], array_column($meta['works_filters'], 'label'));
        self::assertSame(self::HATANO . '?filter=vr', $meta['works_filters'][2]['url']);

        $single = $this->performer('performer-1044864.html', self::KAWAKITA)['metadata'];
        self::assertSame(1, $single['works_last_page']);
        self::assertArrayNotHasKey('works_next_url', $single);
    }

    public function test_profile_works_carry_every_field_of_the_card_and_payload(): void
    {
        $works = $this->performer('performer-26225.html', self::HATANO)['metadata']['works'];

        self::assertCount(100, $works);
        self::assertSame('cjod00543', $works[0]['external_id']);
        self::assertSame('爆乳女将の全裸おもてなし旅館 乳圧むぎゅ♪肉尻びたん♪で即イクッ！2泊3日ず～っと中出し搾られ続けたボク 波多野結衣', $works[0]['title']);
        self::assertSame('https://al.fanza.co.jp/?lurl=https%3A%2F%2Fvideo.dmm.co.jp%2Fav%2Fcontent%2F%3Fid%3Dcjod00543&af_id=ahiruchan-004&ch=api', $works[0]['url']);
        self::assertSame(2180, $works[0]['price']);
        self::assertArrayNotHasKey('on_sale', $works[0]);
        self::assertSame(self::CDN . 'cjod00543/cjod00543pl.jpg', $works[0]['cover_url']);
        self::assertSame(self::CDN . 'cjod00543/cjod00543ps.jpg', $works[0]['thumb_url']);
        self::assertSame(self::CDN . 'cjod00543/cjod00543pt.jpg', $works[0]['list_thumb_url']);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=cjod00543/size=720_480/affi_id=ahiruchan-990/', $works[0]['sample_video_url']);

        self::assertSame('avsa00462', $works[10]['external_id']);
        self::assertSame(1960, $works[10]['price']);
        self::assertSame(2800, $works[10]['list_price']);
        self::assertTrue($works[10]['on_sale']);
        self::assertCount(15, array_filter($works, static fn(array $work): bool => ($work['on_sale'] ?? false) === true));
        self::assertCount(85, array_filter($works, static fn(array $work): bool => isset($work['sample_video_url'])));
        self::assertSame('lulu00421', $works[99]['external_id']);
        self::assertSame(500, $works[99]['price']);
    }

    public function test_profile_images_cover_every_page_image_with_thumbnails(): void
    {
        foreach ([['performer-26225.html', self::HATANO, 100], ['performer-1044864.html', self::KAWAKITA, 98]] as [$fixture, $url, $count]) {
            $meta = $this->performer($fixture, $url)['metadata'];
            $pageImages = (new Crawler($this->loadFixture('avactress/' . $fixture)))->filter('main section div.group img')->count();

            self::assertSame($count, $pageImages, $fixture);
            self::assertCount($pageImages, $meta['images'], $fixture);
            self::assertCount($pageImages, $meta['works'], $fixture);
            self::assertSame(array_column($meta['works'], 'cover_url'), array_column($meta['images'], 'url'), $fixture);
            self::assertSame(array_column($meta['works'], 'thumb_url'), array_column($meta['images'], 'thumb_url'), $fixture);
            self::assertCount($pageImages, array_unique(array_column($meta['images'], 'url')), $fixture);
        }

        $first = $this->performer('performer-26225.html', self::HATANO)['metadata']['images'][0];
        self::assertSame(self::CDN . 'cjod00543/cjod00543pl.jpg', $first['url']);
        self::assertSame(self::CDN . 'cjod00543/cjod00543ps.jpg', $first['thumb_url']);
        self::assertSame('cjod00543', $first['work_id']);
        self::assertStringStartsWith('爆乳女将の全裸おもてなし旅館', $first['caption']);
    }

    public function test_listing_cards_from_real_capture(): void
    {
        $result = $this->listing('performers-popular.html', self::BASE . '/popular');

        self::assertSame('performer', $result->entityType);
        self::assertCount(100, $result->items);
        self::assertSame(self::BASE . '/1099472/%E7%80%AC%E6%88%B8%E7%92%B0%E5%A5%88', $result->items[0]->url);
        $first = $result->items[0]->meta['performer'];
        self::assertSame('1099472', $first['external_id']);
        self::assertSame('瀬戸環奈', $first['name']);
        self::assertSame('瀬戸環奈', $first['name_japanese']);
        self::assertSame('せとかんな', $first['metadata']['name_kana']);
        self::assertNull($first['size_raw']);
        self::assertArrayNotHasKey('bust_cm', $first['metadata']);
        self::assertArrayNotHasKey('age', $first['metadata']);
        self::assertNull($first['profile_image_url']);

        $kawakita = $result->items[3]->meta['performer'];
        self::assertSame('河北彩花', $kawakita['name']);
        self::assertSame(['河北彩伽'], $kawakita['aliases']);
        self::assertSame('河北彩花（河北彩伽）', $kawakita['metadata']['display_name']);
        self::assertSame('B87(E)', $kawakita['size_raw']);
        self::assertSame(87, $kawakita['metadata']['bust_cm']);
        self::assertSame('E', $kawakita['metadata']['cup']);

        $hatano = $result->items[5]->meta['performer'];
        self::assertSame('26225', $hatano['external_id']);
        self::assertSame(88, $hatano['metadata']['bust_cm']);
        self::assertSame('E', $hatano['metadata']['cup']);
        self::assertSame(38, $hatano['metadata']['age']);
    }

    public function test_listing_card_images_with_thumbnails_and_pagination(): void
    {
        $popular = $this->listing('performers-popular.html', self::BASE . '/popular');
        $image = $popular->items[0]->meta['performer']['metadata']['images'][0];

        self::assertSame(self::CDN . 'snos00461/snos00461pl.jpg', $image['url']);
        self::assertSame(self::CDN . 'snos00461/snos00461ps.jpg', $image['thumb_url']);
        self::assertSame('瀬戸環奈', $image['caption']);
        self::assertSame(1, $popular->page);
        self::assertSame(1, $popular->pagination->lastPage);
        self::assertFalse($popular->pagination->hasNextPage);
        self::assertNull($popular->pagination->nextUrl);

        $new = $this->listing('performers-new.html', self::BASE . '/new');
        self::assertCount(100, $new->items);
        self::assertSame(1, $new->pagination->currentPage);
        self::assertSame(7, $new->pagination->lastPage);
        self::assertSame(2, $new->pagination->nextPage);
        self::assertSame(self::BASE . '/new?page=2', $new->pagination->nextUrl);
        self::assertTrue($new->pagination->hasNextPage);

        $withImages = array_filter($new->items, static fn($item): bool => isset($item->meta['performer']['metadata']['images']));
        self::assertCount(94, $withImages);
        $alexa = $new->items[array_key_last(array_filter($new->items, static fn($item): bool => $item->meta['performer']['external_id'] === '1115877'))]->meta['performer'];
        self::assertSame('アレクサ・チェインズ', $alexa['name']);
        self::assertSame('あれくさちぇいんず', $alexa['metadata']['name_kana']);
        self::assertArrayNotHasKey('images', $alexa['metadata']);
    }

    public function test_urls_are_inferred_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('avactress');
        self::assertInstanceOf(AvactressCrawler::class, $crawler);

        $expected = [
            '/26225/%E6%B3%A2%E5%A4%9A%E9%87%8E%E7%B5%90%E8%A1%A3' => CrawlType::PerformerDetail,
            '/26225/%E6%B3%A2%E5%A4%9A%E9%87%8E%E7%B5%90%E8%A1%A3?page=2' => CrawlType::PerformerDetail,
            '/popular' => CrawlType::PerformerListing,
            '/new?page=2' => CrawlType::PerformerListing,
            '/bust' => CrawlType::PerformerListing,
            '/young' => CrawlType::PerformerListing,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/about')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/videos')->matched);
        self::assertSame('avactress', $crawler->name());
    }

    public function test_site_helpers(): void
    {
        self::assertSame('26225', Site::performerId(self::HATANO));
        self::assertNull(Site::performerId(self::BASE . '/popular'));
        self::assertSame(self::CDN . 'a/apl.jpg', Site::largeCover(self::CDN . 'a/aps.jpg'));
        self::assertSame('snos00422', Site::contentId('https://al.fanza.co.jp/?lurl=https%3A%2F%2Fvideo.dmm.co.jp%2Fav%2Fcontent%2F%3Fid%3Dsnos00422&af_id=x&ch=api'));
        self::assertNull(Site::contentId('https://al.fanza.co.jp/?af_id=x'));
    }

    public function test_pages_without_the_expected_markup_are_rejected(): void
    {
        $empty = '<html><body><p>nothing</p></body></html>';

        foreach ([PerformerListing::class, PerformerDetail::class] as $type) {
            try {
                (new $type($this->clientWithHtml($empty)))->execute(new CrawlRequestDto(url: self::HATANO, type: CrawlType::PerformerDetail));
                self::fail($type . ' accepted an empty page');
            } catch (CrawlParseException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
