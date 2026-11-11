<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Hiyoko;

use JOOservices\CrawlerX\Adapters\Hiyoko\HiyokoCrawler;
use JOOservices\CrawlerX\Adapters\Hiyoko\Types\Detail;
use JOOservices\CrawlerX\Adapters\Hiyoko\Types\Listing;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;

final class HiyokoTest extends TestCase
{
    private const BASE = 'https://www.hiyoko-av.com';

    public function test_listing_extracts_each_real_work_card_and_pagination(): void
    {
        foreach ([
            '/title/' => ['listing-page1.html', 1],
            '/title/page/2/' => ['listing-page2.html', 2],
        ] as $path => [$fixture, $expectedPage]) {
            $result = (new Listing($this->clientWithFixture('hiyoko/' . $fixture)))
                ->execute(new CrawlRequestDto(url: self::BASE . $path));

            self::assertNotEmpty($result->items);
            foreach ($result->items as $item) {
                $movie = $item->meta['movie'];
                self::assertMatchesRegularExpression('#^' . self::BASE . '/title/piyo-\d+/$#', $item->url);
                self::assertMatchesRegularExpression('/^PIYO-\d+$/', $movie['external_id']);
                self::assertSame($movie['external_id'], $movie['code']);
                self::assertNotSame('', $movie['title']);
                self::assertMatchesRegularExpression('#^' . self::BASE . '/hiyokosys/wp-content/uploads/.+\.(?:jpg|png)$#i', $movie['cover_url']);
                self::assertSame([], $movie['performers']);
                self::assertSame('Hiyoko', $movie['metadata']['maker']);
            }

            self::assertSame($expectedPage, $result->pagination->currentPage);
            self::assertSame(10, $result->pagination->lastPage);
            self::assertSame($expectedPage + 1, $result->pagination->nextPage);
            self::assertSame(self::BASE . '/title/page/' . ($expectedPage + 1) . '/', $result->pagination->nextUrl);
            self::assertTrue($result->pagination->hasNextPage);
        }
    }

    public function test_detail_extracts_all_labelled_fields_and_deduplicated_work_images(): void
    {
        $result = (new Detail($this->clientWithFixture('hiyoko/detail-piyo-242.html')))
            ->execute(new CrawlRequestDto(url: self::BASE . '/title/piyo-242/'));
        $movie = $result->meta['movie'];

        self::assertSame(self::BASE . '/title/piyo-242/', $result->url);
        self::assertSame('PIYO-242', $movie['external_id']);
        self::assertSame('PIYO-242', $movie['code']);
        self::assertStringStartsWith('ひよこ8周年記念。', $movie['title']);
        self::assertStringStartsWith('【配信開始は11月15日】', $movie['description']);
        self::assertSame('2026-11-19', $movie['date']);
        self::assertSame(120, $movie['duration']);
        self::assertSame('https://www.hiyoko-av.com/hiyokosys/wp-content/uploads/2026/10/PIYO-242-H1-676.jpg', $movie['cover_url']);
        self::assertCount(2, $movie['screenshots']);
        self::assertSame([
            [
                'url' => 'https://www.hiyoko-av.com/hiyokosys/wp-content/uploads/2026/10/PIYO-242-H1-676.jpg',
                'thumbnail_url' => 'https://www.hiyoko-av.com/hiyokosys/wp-content/uploads/2026/10/PIYO-242-H1-676.jpg',
                'metadata' => ['position' => 0],
            ],
            [
                'url' => 'https://www.hiyoko-av.com/hiyokosys/wp-content/uploads/2026/10/PIYO-242-H4.jpg',
                'thumbnail_url' => 'https://www.hiyoko-av.com/hiyokosys/wp-content/uploads/2026/10/PIYO-242-H4.jpg',
                'metadata' => ['position' => 1],
            ],
        ], $movie['screenshots']);
        self::assertSame([], $movie['performers']);
        self::assertSame([], $movie['tags']);

        $metadata = $movie['metadata'];
        self::assertSame('Hiyoko', $metadata['maker']);
        self::assertSame('', $metadata['label']);
        self::assertSame('ピエロ田', $metadata['director']);
        self::assertSame([
            'レーベル' => '',
            '監督' => 'ピエロ田',
            '発売日' => '2026年11月19日',
            '時間' => '120分',
            '品番' => 'PIYO-242',
        ], $metadata['source_fields']);
        self::assertSame(['FANZA通販', 'FANZA動画', 'Amazon通販'], array_column($metadata['purchase_links'], 'name'));
        self::assertCount(3, $metadata['purchase_links']);
        foreach ($metadata['purchase_links'] as $link) {
            self::assertNotSame('', $link['url']);
        }
        self::assertStringContainsString('al.fanza.co.jp', $metadata['purchase_links'][0]['url']);
        self::assertStringContainsString('cid%3D1piyo242', $metadata['purchase_links'][0]['url']);
    }

    public function test_url_detection_matches_only_captured_listing_and_detail_routes(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('hiyoko');

        self::assertInstanceOf(HiyokoCrawler::class, $crawler);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/title/')->crawlType);
        self::assertSame(CrawlType::Listing, $crawler->detectUrl(self::BASE . '/title/page/2/')->crawlType);
        self::assertSame(CrawlType::Detail, $crawler->detectUrl(self::BASE . '/title/piyo-242/')->crawlType);
        self::assertFalse($crawler->detectUrl(self::BASE . '/genre/美少女/')->matched);
        self::assertFalse($crawler->detectUrl(self::BASE . '/title/page/0/')->matched);
    }
}
