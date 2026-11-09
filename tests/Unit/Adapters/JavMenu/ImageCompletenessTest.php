<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\JavMenu;

use JOOservices\CrawlerX\Adapters\JavMenu\Types\Detail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\Listing;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\JavMenu\Types\PerformerListing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

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
            $imageUrls = array_values(array_unique([
                ...$page->filter('meta[property="og:image"]')->each(static fn(Crawler $node): string => (string) $node->attr('content')),
                ...$page->filter('video#seo-main-video[poster]')->each(static fn(Crawler $node): string => (string) $node->attr('poster')),
            ]));
            $expected = array_values(array_filter($imageUrls));
            $movie = $this->item(Detail::class, $fixture, self::BASE . $path, CrawlType::Detail)->meta['movie'];

            self::assertNotEmpty($expected, $fixtureName);
            self::assertEqualsCanonicalizing($expected, [$movie['cover_url']], $fixtureName);
        }
    }

    public function test_work_listings_keep_every_card_cover_and_the_performer_pages_keep_work_covers(): void
    {
        foreach ([
            ['list-censored-page2.html', self::BASE . '/censored?page=2'],
            ['list-maker-440.html', self::BASE . '/censored/maker/440'],
        ] as [$fixtureName, $url]) {
            $fixture = 'javmenu/' . $fixtureName;
            $expected = $this->workCardImages($fixture, $url);
            $result = $this->list(Listing::class, $fixture, $url, CrawlType::Listing);
            $actual = [];
            foreach ($result->items as $item) {
                $actual[$item->url] = $item->meta['movie']['cover_url'] ?? null;
            }

            self::assertNotEmpty($expected, $fixtureName);
            self::assertSame($expected, $actual, $fixtureName);
        }

        $fixture = 'javmenu/actor-83V.html';
        $url = self::BASE . '/actor/83V';
        $expected = $this->workCardImages($fixture, $url);
        $performer = $this->item(PerformerDetail::class, $fixture, $url, CrawlType::PerformerDetail)->meta['performer'];
        $actual = [];
        foreach ($performer['metadata']['works'] as $work) {
            $actual[$work['url']] = $work['cover_url'] ?? null;
        }

        self::assertNotEmpty($expected);
        self::assertSame($expected, $actual);
    }

    public function test_performer_listing_keeps_each_real_profile_image_and_ignores_placeholders(): void
    {
        $fixture = 'javmenu/actor-list.html';
        $url = self::BASE . '/censored/actor';
        $fixtureHtml = $this->loadFixture($fixture);
        $page = new Crawler($fixtureHtml);
        $cards = $page->filter('.category-page.video-list-item');
        $result = $this->list(PerformerListing::class, $fixture, $url, CrawlType::PerformerListing);

        self::assertGreaterThan(0, $cards->count());
        self::assertCount($cards->count(), $result->items);
        foreach ($result->items as $item) {
            self::assertNull($item->meta['performer']['profile_image_url'] ?? null, 'Theme placeholders must be omitted.');
        }

        $realPhoto = 'https://cdn.example.test/actor-profile.jpg';
        $modifiedHtml = preg_replace(
            '/data-src="https:\/\/javmenu\.com\/theme\/javmenu\/images\/actor-placeholder\.jpg"/',
            'data-src="' . $realPhoto . '"',
            $fixtureHtml,
            1,
            $replacements,
        );
        self::assertSame(1, $replacements);
        self::assertIsString($modifiedHtml);
        $modified = (new PerformerListing($this->clientWithMappedResponses([
            $url => ['body' => $modifiedHtml],
        ])))->execute(new CrawlRequestDto(url: $url, type: CrawlType::PerformerListing));

        self::assertSame($realPhoto, $modified->items[0]->meta['performer']['profile_image_url'] ?? null);
        foreach (array_slice($modified->items, 1) as $item) {
            self::assertNull($item->meta['performer']['profile_image_url'] ?? null);
        }
    }

    /** @return array<string, string> work URL => fixture image URL */
    private function workCardImages(string $fixture, string $baseUrl): array
    {
        $images = [];
        $page = new Crawler($this->loadFixture($fixture));

        $page->filter('.category-page.video-list-item')->each(function (Crawler $card) use (&$images, $baseUrl): void {
            $link = $card->filter('.card-body a[href]');
            $image = $card->filter('img.card-img-top');
            if ($link->count() === 0 || $image->count() === 0) {
                return;
            }

            $source = $image->first()->attr('data-src') ?? $image->first()->attr('src');
            if (is_string($source) && $source !== '' && ! str_contains($source, '/theme/')) {
                $images[UriResolver::resolve((string) $link->attr('href'), $baseUrl)] = UriResolver::resolve($source, $baseUrl);
            }
        });

        return $images;
    }
}
