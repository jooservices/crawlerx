<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bomb;

use JOOservices\CrawlerX\Adapters\Bomb\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class ListingTest extends TestCase
{
    private const BASE = 'https://www.bomb.tv/plus';

    private function listing(): CrawlListResultDto
    {
        return (new Listing($this->clientWithFixture('bomb/index.html')))->execute(new CrawlRequestDto(
            url: self::BASE . '/index.html',
            type: CrawlType::Listing,
        ));
    }

    /** @return array<string, mixed> */
    private function item(string $idol): array
    {
        foreach ($this->listing()->items as $item) {
            if (($item->meta['gallery']['external_id'] ?? null) === $idol) {
                return $item->meta['gallery'];
            }
        }
        self::fail('Idol not found in the listing: ' . $idol);
    }

    public function test_listing_has_one_gallery_item_per_idol_and_no_pagination(): void
    {
        $result = $this->listing();

        self::assertSame('gallery', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(108, $result->items);
        self::assertSame(self::BASE . '/idolindex/minami.html', $result->items[0]->url);
        self::assertSame('gallery', $result->items[0]->nextCrawlType);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(1, $result->pagination->lastPage);
        self::assertNull($result->pagination->nextPage);
        self::assertNull($result->pagination->nextUrl);
        self::assertFalse($result->pagination->hasNextPage);
        self::assertSame(['minami', 'mita', 'chitose', 'sumipon'], array_slice(
            array_map(static fn($item): string => $item->meta['gallery']['external_id'], $result->items),
            0,
            4,
        ));
    }

    public function test_featured_banner_pack_fields(): void
    {
        $gallery = $this->item('mita');

        self::assertSame('三田悠貴', $gallery['title']);
        self::assertSame(['三田悠貴'], $gallery['performers']);
        self::assertSame(2, $gallery['photo_count']);
        self::assertSame(1, $gallery['metadata']['pack_count']);
        self::assertNull($gallery['metadata']['name_image_url']);

        $pack = $gallery['metadata']['packs'][0];
        self::assertSame('mita03', $pack['id']);
        self::assertSame(3, $pack['number']);
        self::assertSame('p_mita03', $pack['product_id']);
        self::assertSame('超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $pack['title']);
        self::assertSame('三田悠貴　No.3　超絶やわらかGカップバスト 艶めかしく誘惑する甘い香り', $pack['caption']);
        self::assertNull($pack['catchcopy']);
        self::assertNull($pack['catchcopy_image_url']);
        self::assertFalse($pack['is_new']);
        self::assertSame(60, $pack['image_count']);
        self::assertSame(3, $pack['movie_count']);
        self::assertNull($pack['voice_count']);
        self::assertSame(1000, $pack['price_jpy']);
        self::assertNull($pack['access_days']);
        self::assertSame('http://www.bomb.tv/plus_src/select_payment.php?iid=p_mita03&cid=plus', $pack['purchase_url']);
        self::assertSame(self::BASE . '/sample/mita03.html', $pack['sample_page_url']);
        self::assertSame(self::BASE . '/images/mita03_b.jpg', $pack['cover_url']);
        self::assertSame(self::BASE . '/images/mita03_bs.jpg', $pack['thumbnail_url']);
        self::assertSame(self::BASE . '/sample/images/mita03.jpg', $pack['sample_image_url']);
        self::assertSame('10/01 三田悠貴 No.3追加！', $pack['whatsnew_label']);
        self::assertStringStartsWith('圧倒的なGカップボディ＆“軽トラ女子”で人気の【三田悠貴】衝撃グラビア！', (string) $pack['whatsnew_text']);
        self::assertSame(['contents' => '画像60点 mp4ムービー3本'], $pack['source_fields']);
    }

    public function test_new_badge_and_entities_in_the_whats_new_text(): void
    {
        $pack = $this->item('minami')['metadata']['packs'][0];

        self::assertTrue($pack['is_new']);
        self::assertSame('11/02 南みゆか No.2追加！', $pack['whatsnew_label']);
        self::assertStringStartsWith('アイドルグループSophià la Mode（ソフィアラモード）メンバー', (string) $pack['whatsnew_text']);
    }

    public function test_card_pack_fields_and_images(): void
    {
        $gallery = $this->item('sumipon');

        self::assertSame('すみぽん（高倉菫）', $gallery['title']);
        self::assertSame(self::BASE . '/images/n_sumipon.gif', $gallery['metadata']['name_image_url']);

        $pack = $gallery['metadata']['packs'][0];
        self::assertSame('sumipon01', $pack['id']);
        self::assertSame(1, $pack['number']);
        self::assertSame('ショートカットで天真爛漫な笑顔と健康的な美ボディビキニ！', $pack['title']);
        self::assertSame($pack['title'], $pack['catchcopy']);
        self::assertSame(self::BASE . '/images/sumipon01_c.gif', $pack['catchcopy_image_url']);
        self::assertSame(70, $pack['image_count']);
        self::assertSame(0, $pack['movie_count'], '"※ムービー公開なし" means no movie');
        self::assertSame(1000, $pack['price_jpy']);
        self::assertSame(10, $pack['access_days']);
        self::assertSame('08/03 すみぽん（高倉菫） No.1追加！', $pack['whatsnew_label']);

        $photos = $gallery['photos'];
        self::assertSame([1, 2], array_column($photos, 'position'));
        self::assertSame(self::BASE . '/images/sumipon01.jpg', $photos[0]['image_url']);
        self::assertSame(self::BASE . '/images/sumipon01_s.jpg', $photos[0]['thumbnail_url']);
        self::assertSame(116, $photos[0]['metadata']['width']);
        self::assertSame(150, $photos[0]['metadata']['height']);
        self::assertSame('cover', $photos[0]['metadata']['kind']);
        self::assertSame(self::BASE . '/index.html', $photos[0]['metadata']['source_page_url']);
        self::assertSame(self::BASE . '/sample/images/sumipon01.jpg', $photos[1]['image_url']);
        self::assertNull($photos[1]['thumbnail_url']);
        self::assertSame('sample', $photos[1]['metadata']['kind']);
        self::assertSame(self::BASE . '/sample/sumipon01.html', $photos[1]['metadata']['source_page_url']);
    }

    public function test_voice_content_and_pack_without_whats_new_entry(): void
    {
        $pack = $this->item('koizumi')['metadata']['packs'][0];

        self::assertSame(116, $pack['image_count']);
        self::assertSame(6, $pack['movie_count']);
        self::assertSame(60, $pack['voice_count']);
        self::assertSame(['contents' => '画像116点 ムービー6本 システムボイス60点'], $pack['source_fields']);
        self::assertNull($pack['whatsnew_label']);
        self::assertNull($pack['whatsnew_text']);
    }

    public function test_image_counts_match_the_page(): void
    {
        $packs = [];
        $photos = 0;
        foreach ($this->listing()->items as $item) {
            $gallery = $item->meta['gallery'];
            $photos += count($gallery['photos']);
            array_push($packs, ...$gallery['metadata']['packs']);
        }

        // page: 3 featured covers (700x400) + 105 card covers (116x150) and one thumbnail per pack = 216 images, plus 108 derived sample stills
        self::assertCount(108, $packs);
        self::assertSame(216, $photos);
        self::assertCount(108, array_filter(array_column($packs, 'thumbnail_url')));
        self::assertCount(105, array_filter(array_column($packs, 'catchcopy_image_url')));
        self::assertCount(1, array_filter(array_column($packs, 'is_new')));
        self::assertCount(11, array_filter(array_column($packs, 'whatsnew_label')));
    }

    public function test_page_without_pack_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        (new Listing($this->clientWithHtml('<html><body><p>empty</p></body></html>')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/index.html', type: CrawlType::Listing));
    }
}
