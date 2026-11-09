<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Avactress;

use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerDetail;
use JOOservices\CrawlerX\Adapters\Avactress\Types\PerformerListing;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tests\Unit\Adapters\ImageAuditTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImageCompletenessTest extends ImageAuditTestCase
{
    public function test_each_profile_keeps_every_work_cover_and_thumbnail_pair(): void
    {
        foreach ([
            ['performer-26225.html', 'https://av-actress.com/26225/x'],
            ['performer-1044864.html', 'https://av-actress.com/1044864/x'],
        ] as [$fixtureName, $url]) {
            $fixture = 'avactress/' . $fixtureName;
            $expected = $this->workImages($fixture);
            $performer = $this->item(PerformerDetail::class, $fixture, $url, CrawlType::PerformerDetail)->meta['performer'];
            $actualWorks = [];
            foreach ($performer['metadata']['works'] as $work) {
                $actualWorks[$work['external_id']] = [
                    'cover_url' => $work['cover_url'] ?? null,
                    'thumb_url' => $work['thumb_url'] ?? null,
                ];
            }
            $actualImages = [];
            foreach ($performer['metadata']['images'] as $image) {
                $actualImages[$image['work_id']] = [
                    'cover_url' => $image['url'] ?? null,
                    'thumb_url' => $image['thumb_url'] ?? null,
                ];
            }

            self::assertNotEmpty($expected, $fixtureName);
            self::assertSame($expected, $actualWorks, $fixtureName . ' work covers');
            self::assertSame($expected, $actualImages, $fixtureName . ' image entries');
        }
    }

    public function test_performer_listings_keep_each_real_profile_photo(): void
    {
        foreach ([
            ['performers-new.html', 'https://av-actress.com/new'],
            ['performers-popular.html', 'https://av-actress.com/popular'],
        ] as [$fixtureName, $url]) {
            $fixture = 'avactress/' . $fixtureName;
            $expected = $this->profileImages($fixture);
            $result = $this->list(PerformerListing::class, $fixture, $url, CrawlType::PerformerListing);
            $actual = [];
            foreach ($result->items as $item) {
                $image = $item->meta['performer']['metadata']['images'][0] ?? [];
                if (! is_string($image['url'] ?? null) || $image['url'] === '') {
                    continue;
                }

                $id = $item->meta['performer']['external_id'];
                $actual[$id] = [
                    'url' => $image['url'] ?? null,
                    'thumb_url' => $image['thumb_url'] ?? null,
                ];
            }

            self::assertNotEmpty($expected, $fixtureName);
            self::assertSame($expected, $actual, $fixtureName);
        }
    }

    /** @return array<string, array{cover_url: string, thumb_url: string}> */
    private function workImages(string $fixture): array
    {
        $images = [];
        $page = new Crawler($this->loadFixture($fixture));
        $page->filter('main section a[href*="al.fanza.co.jp"]')->each(function (Crawler $link) use (&$images): void {
            parse_str((string) parse_url((string) $link->attr('href'), PHP_URL_QUERY), $query);
            $target = is_string($query['lurl'] ?? null) ? $query['lurl'] : '';
            parse_str((string) parse_url($target, PHP_URL_QUERY), $targetQuery);
            $id = $targetQuery['id'] ?? null;
            $card = $link->ancestors()->filter('div.group.flex.flex-col')->first();
            $source = $card->filter('img')->count() === 0 ? null : $card->filter('img')->first()->attr('src');
            if (! is_string($id) || ! is_string($source) || $source === '') {
                return;
            }

            $large = preg_replace('/ps\.jpg$/', 'pl.jpg', $source);
            $images[$id] = ['cover_url' => is_string($large) ? $large : $source, 'thumb_url' => $source];
        });

        return $images;
    }

    /** @return array<string, array{url: string, thumb_url: string}> */
    private function profileImages(string $fixture): array
    {
        $images = [];
        $page = new Crawler($this->loadFixture($fixture));
        $page->filter('main section a[href]')->each(function (Crawler $card) use (&$images): void {
            if ($card->filter('h3')->count() === 0 || $card->filter('img')->count() === 0) {
                return;
            }

            $path = trim((string) parse_url((string) $card->attr('href'), PHP_URL_PATH), '/');
            $id = explode('/', $path)[0] ?? '';
            $source = $card->filter('img')->first()->attr('src');
            if ($id === '' || ! is_string($source) || $source === '' || str_contains($source, 'placeholder')) {
                return;
            }

            $large = preg_replace('/ps\.jpg$/', 'pl.jpg', $source);
            $images[$id] = [
                'url' => is_string($large) ? $large : $source,
                'thumb_url' => $source,
            ];
        });

        return $images;
    }
}
