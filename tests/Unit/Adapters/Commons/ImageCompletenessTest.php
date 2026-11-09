<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Commons;

use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Adapters\Commons\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Commons\Types\Listing;
use JOOservices\CrawlerX\Adapters\Commons\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    private const JSON = ['Content-Type' => 'application/json'];

    public function test_category_listing_keeps_every_member_image_and_thumbnail(): void
    {
        $memberFixture = 'commons/listing-root.json';
        $pageFixture = 'commons/listing-root-pages.json';
        $expected = $this->memberImageSlots($memberFixture, $pageFixture);
        $result = (new Listing($this->clientWithMappedResponses($this->responses($memberFixture, $pageFixture))))
            ->execute(new CrawlRequestDto(url: $this->sourceUrl($memberFixture), type: CrawlType::Listing));
        $actual = $this->listImageSlots($result);

        self::assertGreaterThan(0, $expected['images']);
        self::assertGreaterThanOrEqual($expected['images'], $actual['images']);
        self::assertGreaterThanOrEqual($expected['thumbnails'], $actual['thumbnails']);
    }

    public function test_category_gallery_keeps_every_file_photo_and_thumbnail(): void
    {
        $memberFixture = 'commons/gallery-oto-abe.json';
        $pageFixture = 'commons/gallery-oto-abe-pages.json';
        $expected = $this->memberImageSlots($memberFixture, $pageFixture);
        $gallery = (new Gallery($this->clientWithMappedResponses($this->responses($memberFixture, $pageFixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('Category:Oto Abe'), type: CrawlType::Gallery))
            ->meta['gallery'];

        self::assertGreaterThan(0, $expected['images']);
        self::assertGreaterThanOrEqual($expected['images'], count($this->column($gallery['photos'], 'image_url')));
        self::assertGreaterThanOrEqual($expected['thumbnails'], count($this->column($gallery['photos'], 'thumbnail_url')));
    }

    public function test_file_gallery_keeps_its_original_and_thumbnail(): void
    {
        $fixture = 'commons/gallery-file-oto-abe-2021.json';
        $page = $this->decode($fixture)['query']['pages'][0];
        $expected = $this->pageImageSlots($page);
        $gallery = (new Gallery($this->clientWithMappedResponses($this->responses($fixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('File:Oto Abe 2021.jpg'), type: CrawlType::Gallery))
            ->meta['gallery'];

        self::assertSame(1, $expected['images']);
        self::assertGreaterThanOrEqual($expected['images'], count($this->column($gallery['photos'], 'image_url')));
        self::assertGreaterThanOrEqual($expected['thumbnails'], count($this->column($gallery['photos'], 'thumbnail_url')));
    }

    public function test_performer_profile_keeps_its_profile_image_and_thumbnail(): void
    {
        $fixture = 'commons/performer-yuzuki-aikawa.json';
        $page = $this->decode($fixture)['query']['pages'][0];
        $expected = $this->pageImageSlots($page);
        $performer = (new PerformerDetail($this->clientWithMappedResponses($this->responses($fixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('Category:Yuzuki Aikawa'), type: CrawlType::PerformerDetail))
            ->meta['performer'];

        self::assertSame(1, $expected['images']);
        self::assertNotEmpty($performer['profile_image_url']);
        self::assertGreaterThanOrEqual($expected['thumbnails'], count($this->column([$performer['metadata']], 'profile_thumbnail_url')));
    }

    /** @return array{images: int, thumbnails: int} */
    private function memberImageSlots(string $membersFixture, string $pagesFixture): array
    {
        $members = $this->decode($membersFixture)['query']['categorymembers'];
        $pages = $this->decode($pagesFixture)['query']['pages'];
        $byId = [];
        foreach ($pages as $page) {
            $byId[$page['pageid']] = $page;
        }

        $slots = ['images' => 0, 'thumbnails' => 0];
        foreach ($members as $member) {
            $page = $byId[$member['pageid']] ?? null;
            if (! is_array($page)) {
                continue;
            }

            $slots['images'] += $this->pageImageSlots($page)['images'];
            $slots['thumbnails'] += $this->pageImageSlots($page)['thumbnails'];
        }

        return $slots;
    }

    /** @param array<string, mixed> $page
     *  @return array{images: int, thumbnails: int}
     */
    private function pageImageSlots(array $page): array
    {
        if (str_starts_with((string) ($page['title'] ?? ''), 'Category:')) {
            $hasImage = is_string($page['original']['source'] ?? null) || is_string($page['pageimage'] ?? null);
            $hasThumbnail = is_string($page['thumbnail']['source'] ?? null);

            return ['images' => $hasImage ? 1 : 0, 'thumbnails' => $hasThumbnail ? 1 : 0];
        }

        $info = $page['imageinfo'][0] ?? [];

        return [
            'images' => is_string($info['url'] ?? null) ? 1 : 0,
            'thumbnails' => is_string($info['thumburl'] ?? null) ? 1 : 0,
        ];
    }

    /** @return array{images: int, thumbnails: int} */
    private function listImageSlots(CrawlListResultDto $result): array
    {
        $slots = ['images' => 0, 'thumbnails' => 0];
        foreach ($result->items as $item) {
            if (isset($item->meta['gallery'])) {
                $photos = $item->meta['gallery']['photos'];
                $slots['images'] += count($this->column($photos, 'image_url'));
                $slots['thumbnails'] += count($this->column($photos, 'thumbnail_url'));
            } elseif (isset($item->meta['performer'])) {
                $performer = $item->meta['performer'];
                $slots['images'] += is_string($performer['profile_image_url'] ?? null) ? 1 : 0;
                $slots['thumbnails'] += is_string($performer['metadata']['profile_thumbnail_url'] ?? null) ? 1 : 0;
            }
        }

        return $slots;
    }

    /** @return array<string, array{body: string, headers: array<string, string>}> */
    private function responses(string ...$fixtures): array
    {
        $responses = [];
        foreach ($fixtures as $fixture) {
            $metadata = json_decode($this->loadFixture($fixture . '.meta.json'), true, flags: JSON_THROW_ON_ERROR);
            $responses[$metadata['source_url']] = ['body' => $this->loadFixture($fixture), 'headers' => self::JSON];
        }

        return $responses;
    }

    private function sourceUrl(string $fixture): string
    {
        $metadata = json_decode($this->loadFixture($fixture . '.meta.json'), true, flags: JSON_THROW_ON_ERROR);

        return $metadata['source_url'];
    }

    /** @return array<string, mixed> */
    private function decode(string $fixture): array
    {
        return json_decode($this->loadFixture($fixture), true, flags: JSON_THROW_ON_ERROR);
    }
}
