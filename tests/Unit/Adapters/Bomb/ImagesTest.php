<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Bomb;

use JOOservices\CrawlerX\Adapters\Bomb\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Bomb\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const IDOLS = 'https://www.bomb.tv/plus/idolindex/';

    public function test_an_idol_page_keeps_each_pack_cover_both_previews_the_sample_page_image_and_the_name_image(): void
    {
        $gallery = $this->item(Gallery::class, 'bomb/idol-mita.html', self::IDOLS . 'mita.html', CrawlType::Gallery)->meta['gallery'];
        $packs = $gallery['metadata']['packs'];

        self::assertCount(12, $this->column($gallery['photos'], 'image_url'));
        self::assertCount(3, $this->column($packs, 'cover_url'));
        self::assertCount(3, $this->column($packs, 'sample_image_url'));
        self::assertCount(6, array_merge(...array_column($packs, 'preview_urls')));
        self::assertSame(self::IDOLS . 'images/n_mita.gif', $gallery['metadata']['name_image_url']);
    }

    public function test_the_second_idol_page_keeps_its_two_packs(): void
    {
        $gallery = $this->item(Gallery::class, 'bomb/idol-tani.html', self::IDOLS . 'tani.html', CrawlType::Gallery)->meta['gallery'];

        self::assertCount(8, $this->column($gallery['photos'], 'image_url'));
        self::assertSame(self::IDOLS . 'images/tani02.jpg', $gallery['metadata']['packs'][0]['cover_url']);
        self::assertSame(self::IDOLS . 'images/tani02_s2.jpg', $gallery['metadata']['packs'][0]['preview_urls'][1]);
    }

    public function test_every_index_card_keeps_the_cover_and_the_sample_image_of_its_newest_pack(): void
    {
        $result = $this->list(Listing::class, 'bomb/index.html', 'https://www.bomb.tv/plus/index.html', CrawlType::Listing);
        $galleries = $this->metaOf($result->items, 'gallery');

        self::assertCount(108, $galleries);
        self::assertCount(216, array_merge(...array_column($galleries, 'photos')));
        self::assertCount(108, $this->column(array_merge(...array_column($galleries, 'photos')), 'thumbnail_url'));
    }
}
