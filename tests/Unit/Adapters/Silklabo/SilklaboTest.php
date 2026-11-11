<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Silklabo;

use JOOservices\CrawlerX\Adapters\Silklabo\Types\Detail;
use JOOservices\CrawlerX\Adapters\Silklabo\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class SilklaboTest extends TestCase
{
    private const BASE = 'https://www.silklabo.com';

    private const CDN = 'https://s3.ap-northeast-1.amazonaws.com/silk-pics.girls-ch.jp/silk/products/silk_208/';

    public function test_detail_extracts_every_labelled_field_from_real_capture(): void
    {
        $item = $this->detail();
        $movie = $item->meta['movie'];

        self::assertSame('27039', $movie['external_id']);
        self::assertSame('SILK-208', $movie['code']);
        self::assertSame('If　もう一度、あなたに触れたい', $movie['title']);
        self::assertSame(self::CDN . 'silk_208_l.jpg', $movie['cover_url']);
        self::assertSame('2026-11-04', $movie['date']);
        self::assertNull($movie['duration']);
        self::assertSame('恋人の朔耶から突然別れを告げられ、理由も聞かず受け入れる沙月。別れたくないのに素直に縋る事もできず、昔通っていたバーに駆け込みマスターのナツメに「人生やり直したい」と弱音をこぼす。すると突然、過去に想い残りのある魅力的な男達との運命の岐路にタイムリープ！好機と捉え選ばなかった未来に飛び込んでゆき…！', $movie['description']);
        self::assertSame(
            ['保志健斗', '千歳小梅', '友田彩也香', '向理来 (夏目哉大)', '大空太陽', '小鳥遊昴', '木崎凪', '東雲怜弥'],
            array_column($movie['performers'], 'name'),
        );

        $metadata = $movie['metadata'];
        self::assertSame('Silk Labo', $metadata['maker']);
        self::assertSame('KINO', $metadata['director']);
        self::assertSame('2026-11-04', $metadata['release_date']);
        self::assertSame(self::CDN . 'silk_208_s.jpg', $metadata['cover_thumbnail_url']);
        self::assertSame('https://www.dmm.co.jp/litevideo/-/part/=/cid=1silk00208/size=1280_720/', $metadata['sample_video_url']);
        self::assertCount(2, $metadata['purchase_links']);
        self::assertSame('https://al.fanza.co.jp/?lurl=https%3A%2F%2Fvideo.dmm.co.jp%2Fav%2Fcontent%2F%3Fid%3D1silk00208&af_id=mflo14-001&ch=toolbar&ch_id=link', $metadata['purchase_links'][0]['url']);
        self::assertSame('https://boy-studio.com/videos/5254/', $metadata['purchase_links'][1]['url']);
        self::assertSame([
            '品番' => 'SILK-208',
            '商品名' => 'If　もう一度、あなたに触れたい',
            '発売日' => '2026-11-04',
            '監督' => 'KINO',
            '出演者' => '保志健斗 千歳小梅 友田彩也香 向理来 (夏目哉大) 大空太陽 小鳥遊昴 木崎凪 東雲怜弥',
            '商品説明' => '恋人の朔耶から突然別れを告げられ、理由も聞かず受け入れる沙月。別れたくないのに素直に縋る事もできず、昔通っていたバーに駆け込みマスターのナツメに「人生やり直したい」と弱音をこぼす。すると突然、過去に想い残りのある魅力的な男達との運命の岐路にタイムリープ！好機と捉え選ばなかった未来に飛び込んでゆき…！',
        ], $metadata['source_fields']);
        self::assertCount(21, $movie['screenshots']);
        self::assertSame(self::CDN . 'silk_208_l.jpg', $movie['screenshots'][0]['url']);
        self::assertSame(self::CDN . 'silk_208_s.jpg', $movie['screenshots'][0]['thumbnail_url']);
        self::assertSame(1, $movie['screenshots'][0]['metadata']['position']);
    }

    public function test_detail_extracts_each_gallery_image_in_page_order(): void
    {
        $screenshots = $this->detail()->meta['movie']['screenshots'];

        self::assertCount(21, $screenshots);
        self::assertSame(self::CDN . 'silk_208_l.jpg', $screenshots[0]['url']);
        self::assertSame(self::CDN . 'silk_208_s.jpg', $screenshots[0]['thumbnail_url']);
        self::assertSame(1, $screenshots[0]['metadata']['position']);
        for ($index = 1; $index <= 20; ++$index) {
            $image = sprintf(self::CDN . 'ph%02d.jpg', $index);
            self::assertSame($image, $screenshots[$index]['url']);
            self::assertSame($image, $screenshots[$index]['thumbnail_url']);
            self::assertSame($index + 1, $screenshots[$index]['metadata']['position']);
        }
    }

    public function test_listing_page_one_extracts_every_card_field_and_pagination(): void
    {
        $result = (new Listing($this->clientWithFixture('silklabo/listing-page-1.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/product_list/', type: CrawlType::Listing));

        self::assertSame('movie', $result->entityType);
        self::assertSame(1, $result->page);
        self::assertCount(32, $result->items);
        self::assertSame(self::BASE . '/detail/27040', $result->items[0]->url);
        self::assertSame('27040', $result->items[0]->meta['movie']['external_id']);
        self::assertSame('四六時中発情中 7', $result->items[0]->meta['movie']['title']);
        self::assertSame('https://s3.ap-northeast-1.amazonaws.com/silk-pics.girls-ch.jp/silk/products/silk_204/silk_204_s.jpg', $result->items[0]->meta['movie']['cover_url']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(26, $result->pagination->lastPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/product_list/page/2/', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
    }

    public function test_listing_page_two_reads_current_and_next_page_from_its_pager(): void
    {
        $result = (new Listing($this->clientWithFixture('silklabo/listing-page-2.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/product_list/page/2/', type: CrawlType::Listing));

        self::assertSame(2, $result->page);
        self::assertSame(2, $result->pagination->currentPage);
        self::assertSame(26, $result->pagination->lastPage);
        self::assertSame(3, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/product_list/page/3/', $result->pagination->nextUrl);
        self::assertSame('escape', $result->items[0]->meta['movie']['title']);
        self::assertSame('https://s3.ap-northeast-1.amazonaws.com/silk-pics.girls-ch.jp/silk/products/silk_198_1/silk_198_1_s.jpg', $result->items[0]->meta['movie']['cover_url']);
        self::assertSame('27001', $result->items[0]->meta['movie']['external_id']);
    }

    public function test_url_detection_matches_only_captured_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $adapter = $registry->resolve('silklabo');

        self::assertSame(CrawlType::Listing, $adapter->detectUrl(self::BASE . '/product_list/')->crawlType);
        self::assertSame(CrawlType::Listing, $adapter->detectUrl(self::BASE . '/product_list/page/2/')->crawlType);
        self::assertSame(CrawlType::Detail, $adapter->detectUrl(self::BASE . '/detail/27039/')->crawlType);
        self::assertFalse($adapter->detectUrl(self::BASE . '/label_category/silklabo/')->matched);
        self::assertFalse($adapter->detectUrl(self::BASE . '/detail/not-a-number/')->matched);
    }

    private function detail(): \JOOservices\CrawlerX\Dto\CrawlItemResultDto
    {
        return (new Detail($this->clientWithFixture('silklabo/detail-27039.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/detail/27039/', type: CrawlType::Detail));
    }
}
