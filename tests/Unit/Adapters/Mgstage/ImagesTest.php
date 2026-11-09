<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Mgstage;

use JOOservices\CrawlerX\Adapters\Mgstage\Types\Detail;
use JOOservices\CrawlerX\Adapters\Mgstage\Types\Listing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const BASE = 'https://image.mgstage.com/images/luxutv/259luxu/1000/';

    public function test_the_detail_page_keeps_the_large_cover_its_thumbnail_and_every_still_with_its_thumbnail(): void
    {
        $movie = $this->item(Detail::class, 'mgstage/detail-259luxu-1000.html', 'https://www.mgstage.com/product/product_detail/259LUXU-1000/', CrawlType::Detail)->meta['movie'];

        self::assertSame(self::BASE . 'pb_e_259luxu-1000.jpg', $movie['cover_url'], 'the enlarged jacket is the highest resolution');
        self::assertSame(self::BASE . 'pf_o1_259luxu-1000.jpg', $movie['metadata']['cover_thumbnail_url']);
        self::assertCount(16, $this->column($movie['screenshots'], 'url'), 'page has 16 sample stills');
        self::assertCount(16, $this->column($movie['screenshots'], 'thumbnail_url'));

        foreach ($movie['screenshots'] as $index => $shot) {
            self::assertSame(self::BASE . "cap_e_{$index}_259luxu-1000.jpg", $shot['url']);
            self::assertSame(self::BASE . "cap_t1_{$index}_259luxu-1000.jpg", $shot['thumbnail_url']);
        }

        self::assertCount(0, $this->column($movie['performers'], 'profile_image_url'), 'the site has no actor photos');
    }

    public function test_the_second_detail_page_keeps_its_ten_stills_and_both_cover_sizes(): void
    {
        $movie = $this->item(Detail::class, 'mgstage/detail-siro-5767.html', 'https://www.mgstage.com/product/product_detail/SIRO-5767/', CrawlType::Detail)->meta['movie'];

        self::assertSame('https://image.mgstage.com/images/shirouto/siro/5767/pb_e_siro-5767.jpg', $movie['cover_url']);
        self::assertSame('https://image.mgstage.com/images/shirouto/siro/5767/pb_p_siro-5767.jpg', $movie['metadata']['cover_thumbnail_url']);
        self::assertCount(10, $this->column($movie['screenshots'], 'url'));
        self::assertCount(10, $this->column($movie['screenshots'], 'thumbnail_url'));
    }

    public function test_every_listing_card_keeps_its_cover_thumbnail_and_stills(): void
    {
        $result = $this->list(Listing::class, 'mgstage/listing-new.html', 'https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top', CrawlType::Listing);
        $movies = $this->metaOf($result->items, 'movie');

        self::assertCount(6, $this->column($movies, 'cover_url'));
        self::assertCount(6, $this->column(array_column($movies, 'metadata'), 'cover_thumbnail_url'));
        self::assertSame([10, 12, 12, 5, 4, 10], array_map(static fn(array $movie): int => count($movie['screenshots']), $movies));

        self::assertSame('https://image.mgstage.com/images/shirouto/siro/5767/pb_e_siro-5767.jpg', $movies[0]['cover_url']);
        self::assertSame('https://image.mgstage.com/images/shirouto/siro/5767/pf_o2_siro-5767.jpg', $movies[0]['metadata']['cover_thumbnail_url']);
        self::assertSame('https://image.mgstage.com/images/shirouto/siro/5767/cap_e_9_siro-5767.jpg', $movies[0]['screenshots'][9]['url']);
    }
}
