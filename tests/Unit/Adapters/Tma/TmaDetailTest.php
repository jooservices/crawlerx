<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Tma;

use JOOservices\CrawlerX\Adapters\Tma\Types\Detail;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;

final class TmaDetailTest extends TestCase
{
    private const URL = 'https://www.tma.co.jp/products/t38-073';

    private const FILES = 'https://www.tma.co.jp/cdn/shop/files/';

    public function test_detail_maps_every_labelled_field_to_its_standard_key(): void
    {
        $movie = $this->detail('tma/detail-t38-073.html')->meta['movie'];

        self::assertSame('t38-073', $movie['external_id']);
        self::assertSame('T38-073', $movie['code']);
        self::assertSame('義父に犯されメス堕ちする欲求不満の美人妻', $movie['title']);
        self::assertStringStartsWith('旦那との夜の営みがなくなった若妻。', $movie['description']);
        self::assertSame('2026-10-23', $movie['date']);
        self::assertSame(130, $movie['duration']);
        self::assertSame(['五芭', '由衣陽菜', '花衣つばき'], array_column($movie['performers'], 'name'));
        self::assertSame(['人妻熟女'], $movie['tags']);
        self::assertSame('TMA', $movie['metadata']['maker']);
        self::assertSame(['amount' => 4180.0, 'currency' => 'JPY', 'label' => '￥4,180(税込)￥3,800(税抜)'], $movie['metadata']['price']);
        self::assertSame(3800, $movie['metadata']['price_excluding_tax']);
        self::assertSame('4941149090759', $movie['metadata']['jan_code']);
        self::assertSame('https://tma-sample.com/movie/2026_10/T38-073.mp4', $movie['metadata']['sample_video_url']);
    }

    public function test_detail_keeps_the_labelled_rows_and_purchase_link_in_metadata(): void
    {
        $movie = $this->detail('tma/detail-t38-073.html')->meta['movie'];

        self::assertSame([
            '発売元' => 'TMA',
            'タイプ' => '人妻熟女',
            '発売日' => '2026年10月23日 金曜日発売',
            '品番' => 'T38-073',
            '価格' => '￥4,180(税込)￥3,800(税抜)',
            '収録時間' => '130分',
            'JANコード' => '4941149090759',
        ], $movie['metadata']['source_fields']);
        self::assertSame([[
            'name' => 'FANZAの販売ページはこちら',
            'url' => 'https://video.dmm.co.jp/av/content/?id=55t3800073',
        ]], $movie['metadata']['purchase_links']);
    }

    public function test_detail_cover_is_the_package_at_full_size(): void
    {
        $movie = $this->detail('tma/detail-t38-073.html')->meta['movie'];

        self::assertSame(self::FILES . 'T38-073_01.jpg?v=1792558410', $movie['cover_url']);
    }

    public function test_detail_has_one_screenshot_per_work_image_in_page_order(): void
    {
        $screenshots = $this->detail('tma/detail-t38-073.html')->meta['movie']['screenshots'];

        self::assertCount(2, $screenshots);
        self::assertSame(self::FILES . 'T38-073_01.jpg?v=1792558410', $screenshots[0]['url']);
        self::assertSame(self::FILES . 'T38-073_01_1024x1024.jpg?v=1792558410', $screenshots[0]['thumbnail_url']);
        self::assertSame(['position' => 0], $screenshots[0]['metadata']);
        self::assertSame(self::FILES . 'T38-073_02.jpg?v=1792558410', $screenshots[1]['url']);
        self::assertSame(self::FILES . 'T38-073_02_1024x1024.jpg?v=1792558410', $screenshots[1]['thumbnail_url']);
        self::assertSame(['position' => 1], $screenshots[1]['metadata']);
    }

    public function test_detail_reads_only_the_gallery_and_drops_repeated_and_related_images(): void
    {
        $html = <<<'HTML'
            <div class="product-gallery">
              <a class="product-gallery__image product-gallery__image-1" href="//www.tma.co.jp/cdn/shop/files/X-01_2048x.jpg" data-full-size-src="//www.tma.co.jp/cdn/shop/files/X-01.jpg"><noscript><img src="//www.tma.co.jp/cdn/shop/files/X-01_1024x1024.jpg"></noscript></a>
              <a class="product-gallery__image product-gallery__image-2" href="//www.tma.co.jp/cdn/shop/files/X-01_2048x.jpg" data-full-size-src="//www.tma.co.jp/cdn/shop/files/X-01.jpg"><noscript><img src="//www.tma.co.jp/cdn/shop/files/X-01_1024x1024.jpg"></noscript></a>
            </div>
            <div class="block product"><div class="main"><noscript><img src="//www.tma.co.jp/cdn/shop/files/RELATED_1024x1024.jpg"></noscript></div></div>
            <h1 class="product-title">Work</h1>
            HTML;
        $screenshots = $this->detail($html)->meta['movie']['screenshots'];

        self::assertCount(1, $screenshots);
        self::assertSame(self::FILES . 'X-01.jpg', $screenshots[0]['url']);
        self::assertSame(self::FILES . 'X-01_1024x1024.jpg', $screenshots[0]['thumbnail_url']);
    }

    public function test_gallery_item_without_thumbnail_uses_its_full_size_link_and_empty_links_are_skipped(): void
    {
        $html = <<<'HTML'
            <div class="product-gallery">
              <a class="product-gallery__image product-gallery__image-1" href="//www.tma.co.jp/cdn/shop/files/Y-01_2048x.jpg"></a>
              <a class="product-gallery__image product-gallery__image-2" data-full-size-src=""></a>
            </div>
            <h1 class="product-title">Work</h1>
            HTML;
        $screenshots = $this->detail($html)->meta['movie']['screenshots'];

        self::assertCount(1, $screenshots);
        self::assertSame(self::FILES . 'Y-01_2048x.jpg', $screenshots[0]['url']);
        self::assertSame(self::FILES . 'Y-01_2048x.jpg', $screenshots[0]['thumbnail_url']);
    }

    public function test_page_without_a_gallery_is_a_parse_error(): void
    {
        $this->expectException(CrawlParseException::class);

        $this->detail('<h1 class="product-title">Work</h1>');
    }

    private function detail(string $fixtureOrHtml): CrawlItemResultDto
    {
        $client = str_contains($fixtureOrHtml, '<') ? $this->clientWithHtml($fixtureOrHtml) : $this->clientWithFixture($fixtureOrHtml);

        return (new Detail($client))->execute(new CrawlRequestDto(url: self::URL));
    }
}
