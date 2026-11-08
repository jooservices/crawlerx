<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Jav321;

use JOOservices\CrawlerX\Adapters\Jav321\Types\Detail;
use JOOservices\CrawlerX\Adapters\Jav321\Types\Listing;
use JOOservices\CrawlerX\Adapters\Jav321\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    public function test_the_movie_page_keeps_cover_thumbnail_sample_video_poster_stills_and_cast_photos(): void
    {
        $movie = $this->item(Detail::class, 'jav321/detail-ssis00001.html', 'https://www.jav321.com/video/ssis00001', CrawlType::Detail)->meta['movie'];

        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001pl.jpg', $movie['cover_url']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001ps.jpg', $movie['metadata']['cover_thumbnail_url']);
        self::assertSame('http://pics.dmm.co.jp/digital/video/ssis00001/ssis00001pl.jpg', $movie['metadata']['sample_video_poster_url'], 'the double slash of the poster is cleaned');
        self::assertCount(10, $this->column($movie['screenshots'], 'url'));
        self::assertCount(2, $this->column($movie['performers'], 'profile_image_url'));
        self::assertSame('http://pics.dmm.co.jp/mono/actjpgs/aoi_tukasa.jpg', $movie['performers'][0]['profile_image_url']);
    }

    public function test_both_language_pages_of_a_movie_keep_the_same_image_slots(): void
    {
        foreach (['jav321/detail-118rtp00020.html' => 'https://www.jav321.com/video/118rtp00020', 'jav321/detail-en-118rtp00020.html' => 'https://en.jav321.com/video/118rtp00020'] as $fixture => $url) {
            $movie = $this->item(Detail::class, $fixture, $url, CrawlType::Detail)->meta['movie'];

            self::assertSame('http://pics.dmm.co.jp/digital/video/118rtp00020/118rtp00020pl.jpg', $movie['cover_url']);
            self::assertSame('http://pics.dmm.co.jp/digital/video/118rtp00020/118rtp00020pl.jpg', $movie['metadata']['sample_video_poster_url']);
            self::assertCount(10, $this->column($movie['screenshots'], 'url'));
        }
    }

    public function test_every_listing_card_keeps_its_cover(): void
    {
        $byDate = $this->list(Listing::class, 'jav321/listing-release-date-1.html', 'https://www.jav321.com/list/release_date/1', CrawlType::Listing);
        $byStar = $this->list(Listing::class, 'jav321/listing-star-1012844.html', 'https://www.jav321.com/star/1012844/1', CrawlType::Listing);

        self::assertCount(48, $this->column($this->metaOf($byDate->items, 'movie'), 'cover_url'));
        self::assertCount(48, $this->column($this->metaOf($byStar->items, 'movie'), 'cover_url'));
    }

    public function test_every_performer_card_keeps_its_photo(): void
    {
        $result = $this->list(PerformerListing::class, 'jav321/performer-listing-stars-1.html', 'https://www.jav321.com/stars/1', CrawlType::PerformerListing);

        self::assertCount(41, $this->column($this->metaOf($result->items, 'performer'), 'profile_image_url'));
    }
}
