<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Commons;

use JOOservices\CrawlerX\Adapters\Commons\Site;
use JOOservices\CrawlerX\Adapters\Commons\Types\Gallery;
use JOOservices\CrawlerX\Adapters\Commons\Types\Listing;
use JOOservices\CrawlerX\Adapters\Commons\Types\PerformerDetail;
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
        $expected = $this->memberImages($memberFixture, $pageFixture);
        $result = (new Listing($this->clientWithMappedResponses($this->responses($memberFixture, $pageFixture))))
            ->execute(new CrawlRequestDto(url: $this->sourceUrl($memberFixture), type: CrawlType::Listing));
        $actual = [];
        foreach ($result->items as $item) {
            if (isset($item->meta['gallery'])) {
                $gallery = $item->meta['gallery'];
                $id = (string) $gallery['external_id'];
                $actual[$id] = [
                    'kind' => 'gallery',
                    'photos' => array_map(static fn(array $photo): array => [
                        'image_url' => $photo['image_url'] ?? null,
                        'thumbnail_url' => $photo['thumbnail_url'] ?? null,
                    ], $gallery['photos']),
                ];
            } else {
                $performer = $item->meta['performer'];
                $id = (string) $performer['external_id'];
                $actual[$id] = [
                    'kind' => 'performer',
                    'profile_image_url' => $performer['profile_image_url'] ?? null,
                    'profile_thumbnail_url' => $performer['metadata']['profile_thumbnail_url'] ?? null,
                ];
            }
        }

        self::assertNotEmpty(array_filter($expected, static fn(array $image): bool => ($image['kind'] ?? '') === 'performer' && ($image['profile_image_url'] ?? null) !== null));
        self::assertSame($expected, $actual);
    }

    public function test_category_gallery_keeps_every_file_photo_and_thumbnail(): void
    {
        $memberFixture = 'commons/gallery-oto-abe.json';
        $pageFixture = 'commons/gallery-oto-abe-pages.json';
        $expected = $this->memberImages($memberFixture, $pageFixture);
        $gallery = (new Gallery($this->clientWithMappedResponses($this->responses($memberFixture, $pageFixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('Category:Oto Abe'), type: CrawlType::Gallery))
            ->meta['gallery'];

        $expectedPhotos = [];
        foreach ($expected as $entry) {
            if (($entry['kind'] ?? null) === 'gallery') {
                array_push($expectedPhotos, ...$entry['photos']);
            }
        }
        $actualPhotos = array_map(static fn(array $photo): array => [
            'image_url' => $photo['image_url'] ?? null,
            'thumbnail_url' => $photo['thumbnail_url'] ?? null,
        ], $gallery['photos']);

        self::assertNotEmpty($expectedPhotos);
        self::assertSame($expectedPhotos, $actualPhotos);
    }

    public function test_file_gallery_keeps_its_original_and_thumbnail(): void
    {
        $fixture = 'commons/gallery-file-oto-abe-2021.json';
        $page = $this->decode($fixture)['query']['pages'][0];
        $expected = $this->pageImages($page);
        $gallery = (new Gallery($this->clientWithMappedResponses($this->responses($fixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('File:Oto Abe 2021.jpg'), type: CrawlType::Gallery))
            ->meta['gallery'];

        self::assertSame([[
            'image_url' => $expected['image_url'],
            'thumbnail_url' => $expected['thumbnail_url'],
        ]], array_map(static fn(array $photo): array => [
            'image_url' => $photo['image_url'] ?? null,
            'thumbnail_url' => $photo['thumbnail_url'] ?? null,
        ], $gallery['photos']));
    }

    public function test_performer_profile_keeps_its_profile_image_and_thumbnail(): void
    {
        $fixture = 'commons/performer-yuzuki-aikawa.json';
        $page = $this->decode($fixture)['query']['pages'][0];
        $expected = $this->pageImages($page);
        $performer = (new PerformerDetail($this->clientWithMappedResponses($this->responses($fixture))))
            ->execute(new CrawlRequestDto(url: Site::pageUrl('Category:Yuzuki Aikawa'), type: CrawlType::PerformerDetail))
            ->meta['performer'];

        self::assertSame($expected['image_url'], $performer['profile_image_url']);
        self::assertSame($expected['thumbnail_url'], $performer['metadata']['profile_thumbnail_url'] ?? null);
    }

    /** @return array<string, array<string, mixed>> */
    private function memberImages(string $membersFixture, string $pagesFixture): array
    {
        $members = $this->decode($membersFixture)['query']['categorymembers'];
        $pages = $this->decode($pagesFixture)['query']['pages'];
        $byId = [];
        foreach ($pages as $page) {
            $byId[$page['pageid']] = $page;
        }

        $images = [];
        foreach ($members as $member) {
            $page = $byId[$member['pageid']] ?? null;
            if (! is_array($page)) {
                continue;
            }

            $id = (string) $member['pageid'];
            if (str_starts_with((string) ($page['title'] ?? ''), 'Category:')) {
                $profile = $this->pageImages($page);
                $images[$id] = [
                    'kind' => 'performer',
                    'profile_image_url' => $profile['image_url'],
                    'profile_thumbnail_url' => $profile['thumbnail_url'],
                ];
            } else {
                $images[$id] = [
                    'kind' => 'gallery',
                    'photos' => $this->pageImages($page)['photos'],
                ];
            }
        }

        return $images;
    }

    /** @param array<string, mixed> $page
     *  @return array{image_url: ?string, thumbnail_url: ?string, photos?: list<array{image_url: string, thumbnail_url: ?string}>}
     */
    private function pageImages(array $page): array
    {
        if (str_starts_with((string) ($page['title'] ?? ''), 'Category:')) {
            $image = is_string($page['original']['source'] ?? null)
                ? $page['original']['source']
                : (is_string($page['pageimage'] ?? null)
                    ? 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode(str_replace(' ', '_', $page['pageimage']))
                    : null);

            return [
                'image_url' => is_string($image) ? $this->cleanFixtureMediaUrl($image) : null,
                'thumbnail_url' => is_string($page['thumbnail']['source'] ?? null)
                    ? $this->cleanFixtureMediaUrl($page['thumbnail']['source'])
                    : null,
            ];
        }

        $info = $page['imageinfo'][0] ?? [];

        return [
            'image_url' => is_string($info['url'] ?? null) ? $this->cleanFixtureMediaUrl($info['url']) : null,
            'thumbnail_url' => is_string($info['thumburl'] ?? null) ? $this->cleanFixtureMediaUrl($info['thumburl']) : null,
            'photos' => is_string($info['url'] ?? null) ? [[
                'image_url' => $this->cleanFixtureMediaUrl($info['url']),
                'thumbnail_url' => is_string($info['thumburl'] ?? null) ? $this->cleanFixtureMediaUrl($info['thumburl']) : null,
            ]] : [],
        ];
    }

    private function cleanFixtureMediaUrl(string $url): string
    {
        $clean = preg_replace('/\?utm_[^#]*$/', '', $url);

        return is_string($clean) ? $clean : $url;
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
