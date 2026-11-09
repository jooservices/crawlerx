<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavMenu;

use JOOservices\CrawlerX\Adapters\JavMenu\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    private const BASE = 'https://javmenu.com';

    public function test_each_work_detail_keeps_its_page_poster_without_related_covers(): void
    {
        foreach ([
            ['detail-ABF-386.html', '/ABF-386'],
            ['detail-DSOD-078.html', '/DSOD-078'],
            ['detail-DSOD-078-en.html', '/en/DSOD-078'],
        ] as [$fixtureName, $path]) {
            $fixture = 'javmenu/' . $fixtureName;
            $page = new Crawler($this->loadFixture($fixture));
            $imageUrls = [
                ...$page->filter('meta[property="og:image"]')->each(static fn(Crawler $node): string => (string) $node->attr('content')),
                ...$page->filter('video#seo-main-video[poster]')->each(static fn(Crawler $node): string => (string) $node->attr('poster')),
            ];
            $expected = count(array_unique(array_filter($imageUrls)));
            $movie = $this->item(Detail::class, $fixture, self::BASE . $path, CrawlType::Detail)->meta['movie'];

            self::assertGreaterThan(0, $expected, $fixtureName);
            self::assertNotEmpty($movie['cover_url'], $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column([$movie], 'cover_url')), $fixtureName);
        }
    }

    public function test_work_listings_keep_every_card_cover_and_the_performer_pages_keep_work_covers(): void
    {
        foreach ([
            ['list-censored-page2.html', self::BASE . '/censored?page=2'],
            ['list-maker-440.html', self::BASE . '/censored/maker/440'],
        ] as [$fixtureName, $url]) {
            $fixture = 'javmenu/' . $fixtureName;
            $expected = $this->realCardImages($fixture);
            $result = $this->list(Listing::class, $fixture, $url, CrawlType::Listing);
            $movies = $this->metaOf($result->items, 'movie');

            self::assertGreaterThan(0, $expected, $fixtureName);
            self::assertGreaterThanOrEqual($expected, count($this->column($movies, 'cover_url')), $fixtureName);
        }

        $fixture = 'javmenu/actor-83V.html';
        $expected = $this->realCardImages($fixture);
        $performer = $this->item(PerformerDetail::class, $fixture, self::BASE . '/actor/83V', CrawlType::PerformerDetail)->meta['performer'];

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual($expected, count(array_filter(
            $performer['metadata']['works'],
            static fn(array $work): bool => is_string($work['cover_url'] ?? null) && $work['cover_url'] !== '',
        )));
    }

    public function test_performer_listing_keeps_each_real_profile_image_and_ignores_placeholders(): void
    {
        $fixture = 'javmenu/actor-list.html';
        $expected = $this->realCardImages($fixture);
        $result = $this->list(PerformerListing::class, $fixture, self::BASE . '/censored/actor', CrawlType::PerformerListing);
        $performers = $this->metaOf($result->items, 'performer');

        self::assertGreaterThanOrEqual($expected, count($this->column($performers, 'profile_image_url')));
    }

    private function realCardImages(string $fixture): int
    {
        $page = new Crawler($this->loadFixture($fixture));

        return $page->filter('.category-page.video-list-item')->reduce(static function (Crawler $card): bool {
            foreach (['data-src', 'src'] as $attribute) {
                $url = $card->filter('img.card-img-top')->count() > 0 ? $card->filter('img.card-img-top')->first()->attr($attribute) : null;
                if (is_string($url) && $url !== '' && ! str_contains($url, '/theme/')) {
                    return true;
                }
            }

            return false;
        })->count();
    }
}
