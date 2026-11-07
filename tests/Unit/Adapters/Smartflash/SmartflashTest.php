<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Smartflash;

use JOOservices\CrawlerX\Adapters\Smartflash\SmartflashCrawler;
use JOOservices\CrawlerX\Adapters\Smartflash\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Smartflash\Types\Listing;
use JOOservices\CrawlerX\Adapters\Smartflash\Urls;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\TestCase;

final class SmartflashTest extends TestCase
{
    private const BASE = 'https://smart-flash.jp';

    private const UPLOADS = 'https://data.smart-flash.jp/wp-content/uploads/2026/10/';

    private const ARTICLE = self::BASE . '/gravure/idol/432913/';

    /** @return array<string, mixed> */
    private function gallery(string $url = self::ARTICLE): array
    {
        $client = $this->clientWithMappedResponses([
            self::ARTICLE => ['body' => $this->loadFixture('smartflash/gallery-432913.html')],
            self::ARTICLE . 'image/1/' => ['body' => $this->loadFixture('smartflash/gallery-432913-image-1.html')],
        ]);

        return (new Gallery($client))->execute(new CrawlRequestDto(url: $url, type: CrawlType::Gallery))->meta['gallery'];
    }

    public function test_gallery_fields(): void
    {
        $gallery = $this->gallery();

        self::assertSame('432913', $gallery['external_id']);
        self::assertSame('SKE48大村杏、2作連続表題曲センターの矜持「20周年の時には、ファンの皆さんの期待に応えたい」', $gallery['title']);
        self::assertSame('2026-10-10', $gallery['date']);
        self::assertSame(['アイドル'], $gallery['categories']);
        self::assertSame(['SKE48', 'センター', '大村杏'], $gallery['tags']);
        self::assertSame(['大村 杏'], $gallery['performers']);
        self::assertSame('『FLASH』編集部', $gallery['uploader']);
        self::assertSame(self::BASE . '/credit/flash/', $gallery['uploader_url']);
        self::assertSame(10, $gallery['photo_count']);
    }

    public function test_gallery_metadata(): void
    {
        $metadata = $this->gallery()['metadata'];

        self::assertSame('2026-10-10T20:00:40+09:00', $metadata['published_at']);
        self::assertSame('2026-10-10T20:00:40+09:00', $metadata['modified_at']);
        self::assertSame(
            [self::BASE . '/tag/ske48/', self::BASE . '/tag/%e3%82%bb%e3%83%b3%e3%82%bf%e3%83%bc/', self::BASE . '/tag/%e5%a4%a7%e6%9d%91%e6%9d%8f/'],
            $metadata['tag_urls'],
        );
        self::assertSame('週刊FLASH 2026年10月20日・27日合併号', $metadata['source']['name']);
        self::assertStringStartsWith(self::BASE . '/source/', $metadata['source']['url']);
        self::assertSame('https://data.smart-flash.jp/wp-content/uploads/2025/01/20141448/smartflash.jpg', $metadata['author_image_url']);
        self::assertStringStartsWith('『FLASH』は、株式会社光文社が1986年11月に創刊した', $metadata['author_bio']);
        self::assertSame(
            ['url' => self::UPLOADS . '10130453/omura_anzu1_thumb.jpg', 'width' => 1200, 'height' => 800, 'mime_type' => 'image/jpeg'],
            $metadata['cover'],
        );
        self::assertSame(self::ARTICLE . 'image/1/', $metadata['viewer_url']);
        self::assertCount(4, $metadata['body']);
        self::assertStringStartsWith('「私のメンバーカラーが紫なんです。', $metadata['body'][0]);
        self::assertSame('おおむらあんず', $metadata['profile']['reading']);
        self::assertStringStartsWith('21歳 2005年9月20日生まれ 愛知県出身', $metadata['profile']['text']);
        self::assertSame(
            [['role' => '写真', 'name' => '佐藤容平'], ['role' => 'スタイリスト', 'name' => '米丸友子'], ['role' => 'ヘアメイク', 'name' => '池田ふみ（GiGGLE）']],
            $metadata['credits'],
        );
        self::assertArrayNotHasKey('description', $metadata);
    }

    public function test_gallery_photos_cover_every_page_image_in_order(): void
    {
        $photos = $this->gallery()['photos'];
        $expected = [
            '10130450/omura_anzu1_1.jpg', '10130458/omura_anzu2_1.jpg', '10130502/omura_anzu3_1.jpg',
            '10130506/omura_anzu4_1.jpg', '10130511/omura_anzu5_1.jpg', '10130516/omura_anzu6_1.jpg',
            '10130522/omura_anzu7_1.jpg', '10130527/omura_anzu8_1.jpg', '10130533/omura_anzu9_1.jpg',
            '10130537/omura_anzu10_1.jpg',
        ];

        self::assertCount(10, $photos);
        foreach ($expected as $index => $path) {
            self::assertSame(self::UPLOADS . $path, $photos[$index]['image_url']);
            self::assertSame($photos[$index]['image_url'], $photos[$index]['url']);
            self::assertSame($index + 1, $photos[$index]['position']);
            self::assertNull($photos[$index]['thumbnail_url']);
            self::assertSame('大村 杏', $photos[$index]['metadata']['caption']);
            self::assertSame('image/jpeg', $photos[$index]['metadata']['mime_type']);
        }
    }

    public function test_gallery_photo_metadata_merges_article_and_viewer(): void
    {
        $photos = $this->gallery()['photos'];

        self::assertSame(1200, $photos[0]['metadata']['width']);
        self::assertSame(1800, $photos[0]['metadata']['height']);
        self::assertSame(10, $photos[0]['metadata']['viewer_index']);
        self::assertStringStartsWith('SKE48大村杏', $photos[0]['metadata']['alt']);
        self::assertSame(1, $photos[1]['metadata']['viewer_index']);
        self::assertSame('大村 杏', $photos[1]['metadata']['alt']);
        self::assertSame(9, $photos[9]['metadata']['viewer_index']);
    }

    public function test_gallery_excludes_related_and_ranking_images(): void
    {
        $urls = array_column($this->gallery()['photos'], 'image_url');

        foreach ($urls as $url) {
            self::assertStringContainsString('/omura_anzu', $url);
            self::assertStringNotContainsString('-190x127', $url);
            self::assertStringNotContainsString('/themes/', $url);
        }
    }

    public function test_viewer_url_is_normalised_to_the_article(): void
    {
        $gallery = $this->gallery(self::ARTICLE . 'image/1/?rf=2');

        self::assertSame('432913', $gallery['external_id']);
        self::assertCount(10, $gallery['photos']);
    }

    public function test_article_without_viewer_keeps_article_images(): void
    {
        $html = preg_replace('#<div class="linkPages">.*?</div>#s', '', $this->loadFixture('smartflash/gallery-432913.html'));
        $result = (new Gallery($this->clientWithHtml((string) $html)))->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery));

        self::assertCount(1, $result->meta['gallery']['photos']);
        self::assertArrayNotHasKey('viewer_url', $result->meta['gallery']['metadata']);
    }

    public function test_gallery_dates_fall_back_to_the_article_header_without_json_ld(): void
    {
        $html = preg_replace('#<script type="application/ld\+json">.*?</script>#s', '', $this->loadFixture('smartflash/gallery-432913.html'));
        $html = preg_replace('#<div class="linkPages">.*?</div>#s', '', (string) $html);
        $metadata = (new Gallery($this->clientWithHtml((string) $html)))->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery))->meta['gallery']['metadata'];

        self::assertSame('2026-10-10T20:00:00+09:00', $metadata['published_at']);
        self::assertSame('2026-10-10T20:00:00+09:00', $metadata['modified_at']);
        self::assertArrayNotHasKey('cover', $metadata);
    }

    public function test_gallery_without_photos_fails(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Gallery($this->clientWithHtml('<html><body><div id="main"><h1 class="headline7">x</h1></div></body></html>')))
            ->execute(new CrawlRequestDto(url: self::ARTICLE, type: CrawlType::Gallery));
    }

    public function test_listing_cards(): void
    {
        $result = (new Listing($this->clientWithFixture('smartflash/listing-idol-page1.html')))->execute(new CrawlRequestDto(url: self::BASE . '/gravure/idol/'));

        self::assertSame('gallery', $result->entityType);
        self::assertCount(30, $result->items);
        $first = $result->items[0];
        self::assertSame(self::BASE . '/gravure/idol/436817/', $first->url);
        self::assertSame('gallery', $first->nextCrawlType);
        $card = $first->meta['gallery'];
        self::assertSame('436817', $card['external_id']);
        self::assertSame('フリーアナウンサー内田敦子が初写真集！「目の前の人や、一緒に時間を過ごしてくださる方への気配りを大切に」', $card['title']);
        self::assertSame('2026-11-01', $card['date']);
        self::assertSame(['アイドル'], $card['categories']);
        self::assertSame(['内田敦子', '撮影', '写真集'], $card['tags']);
        self::assertSame(self::BASE . '/gravure/idol/', $card['metadata']['category_url']);
        self::assertSame(
            [self::BASE . '/tag/%e5%86%85%e7%94%b0%e6%95%a6%e5%ad%90/', self::BASE . '/tag/%e6%92%ae%e5%bd%b1/', self::BASE . '/tag/%e5%86%99%e7%9c%9f%e9%9b%86/'],
            $card['metadata']['tag_urls'],
        );
        self::assertSame('週刊FLASH 2026年11月10日・17日合併号', $card['metadata']['source']['name']);
        self::assertStringStartsWith(self::BASE . '/source/', $card['metadata']['source']['url']);
        self::assertSame(
            [
                'url' => 'https://data.smart-flash.jp/wp-content/uploads/2026/11/01131716/uchida_atsuko1_thumb.jpg',
                'thumbnail_url' => 'https://data.smart-flash.jp/wp-content/uploads/2026/11/01131716/uchida_atsuko1_thumb-190x127.jpg',
                'mime_type' => 'image/jpeg',
            ],
            $card['metadata']['cover'],
        );
    }

    public function test_listing_cards_without_source_have_no_source_field(): void
    {
        $result = (new Listing($this->clientWithFixture('smartflash/listing-idol-page1.html')))->execute(new CrawlRequestDto(url: self::BASE . '/gravure/idol/'));
        $noSource = array_filter($result->items, static fn($item): bool => ! isset($item->meta['gallery']['metadata']['source']));

        self::assertNotEmpty($noSource);
        self::assertCount(30, array_unique(array_map(static fn($item): string => $item->url, $result->items)));
    }

    public function test_listing_pagination(): void
    {
        $pagination = (new Listing($this->clientWithFixture('smartflash/listing-idol-page1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/gravure/idol/'))->pagination;

        self::assertSame(1, $pagination->currentPage);
        self::assertSame(2, $pagination->nextPage);
        self::assertSame(self::BASE . '/gravure/idol/2/', $pagination->nextUrl);
        self::assertSame(156, $pagination->lastPage);
        self::assertTrue($pagination->hasNextPage);
    }

    public function test_listing_without_cards_fails(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><div id="main"></div></body></html>')))->execute(new CrawlRequestDto(url: self::BASE . '/gravure/idol/'));
    }

    public function test_url_detection_infers_listing_and_gallery_without_overrides(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('smartflash');
        self::assertInstanceOf(SmartflashCrawler::class, $crawler);
        self::assertSame('smartflash', $crawler->name());

        $expected = [
            '/gravure/' => CrawlType::Listing,
            '/gravure/idol/' => CrawlType::Listing,
            '/gravure/idol/2/' => CrawlType::Listing,
            '/gravure/idol/432913/' => CrawlType::Gallery,
            '/gravure/idol/432913/?rf=2' => CrawlType::Gallery,
            '/gravure/idol/432913/image/1/' => CrawlType::Gallery,
        ];
        foreach ($expected as $path => $type) {
            $detected = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($detected->matched, $path);
            self::assertSame($type, $detected->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/tag/ske48/')->matched);
    }

    public function test_url_helpers(): void
    {
        self::assertSame(self::ARTICLE, Urls::article(self::ARTICLE . 'image/3/?rf=2'));
        self::assertSame('not a url', Urls::article('not a url'));
        self::assertNull(Urls::externalId(self::BASE . '/gravure/idol/'));
        self::assertSame('432913', Urls::externalId(self::BASE . '/gravure/idol/432913'));
        self::assertSame('https://x.test/a/b_thumb.png', Urls::original('https://x.test/a/b_thumb-190x127.png'));
        self::assertSame('image/png', Urls::mimeType('https://x.test/a.png'));
        self::assertSame('image/webp', Urls::mimeType('https://x.test/a.webp'));
        self::assertSame('image/gif', Urls::mimeType('https://x.test/a.gif'));
        self::assertNull(Urls::mimeType('https://x.test/a.bin'));
    }
}
