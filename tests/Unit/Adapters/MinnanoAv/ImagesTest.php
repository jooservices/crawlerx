<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\MinnanoAv;

use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Detail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\Listing;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImagesTest extends ImageAuditTestCase
{
    private const BASE = 'https://www.minnano-av.com/';

    public function test_every_actress_row_keeps_its_photo(): void
    {
        $result = $this->list(PerformerListing::class, 'minnanoav/listing_page1.html', self::BASE . 'actress_list.php', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertCount(28, $performers);
        self::assertCount(28, $this->column($performers, 'profile_image_url'));
        self::assertSame(self::BASE . 'p_actress_125_125/339/875339.jpg', $performers[0]['profile_image_url']);
    }

    public function test_the_actress_page_keeps_the_portrait_and_the_lazy_loaded_jackets_of_the_works_table(): void
    {
        $performer = $this->item(PerformerDetail::class, 'minnanoav/detail_rich.html', self::BASE . 'actress945093.html', CrawlType::PerformerDetail)->meta['performer'];

        self::assertStringStartsWith(self::BASE . 'p_actress_125_125/018/945093.jpg', $performer['profile_image_url']);
        $works = $performer['metadata']['filmography'];
        self::assertCount(20, $works);
        self::assertCount(20, $this->column($works, 'cover_url'));
        self::assertSame(self::BASE . 'av407286.html', $works[0]['url']);
        self::assertSame(self::BASE . 'p_package/286/407286.jpg', $works[0]['cover_url']);
        self::assertStringNotContainsString('blank.gif', implode(' ', $this->column($works, 'cover_url')));
    }

    public function test_the_movie_page_keeps_the_jacket_and_every_cast_photo(): void
    {
        $movie = $this->item(Detail::class, 'minnanoav/movie-detail.html', self::BASE . 'av159081.html', CrawlType::Detail)->meta['movie'];

        self::assertSame(self::BASE . 'p_package/2605/159081.jpg', $movie['cover_url']);
        self::assertCount(5, $movie['performers']);
        self::assertCount(5, $this->column($movie['performers'], 'profile_image_url'));
        self::assertSame(self::BASE . 'p_actress_125_125/027/125605.jpg?new', $movie['performers'][0]['profile_image_url']);
    }

    public function test_every_work_row_keeps_its_jacket(): void
    {
        $result = $this->list(Listing::class, 'minnanoav/movie-listing.html', self::BASE . 'actress.php?actress_id=945093', CrawlType::Listing);

        self::assertCount(20, $this->column($this->metaOf($result->items, 'movie'), 'cover_url'));
    }
}
