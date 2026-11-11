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
            '/title/' => [
                'listing-page1.html',
                1,
                [
                    'PIYO-242', 'PIYO-241', 'PIYO-239', 'PIYO-240', 'PIYO-238', 'PIYO-234',
                    'PIYO-236', 'PIYO-237', 'PIYO-235', 'PIYO-230', 'PIYO-233', 'PIYO-232',
                    'PIYO-229', 'PIYO-228', 'PIYO-227', 'PIYO-226', 'PIYO-225', 'PIYO-224',
                    'PIYO-223', 'PIYO-222', 'PIYO-211', 'PIYO-221', 'PIYO-220', 'PIYO-216',
                    'PIYO-219', 'PIYO-217', 'PIYO-194', 'PIYO-209', 'PIYO-215', 'PIYO-214',
                ],
                [
                    '2985ee5fac6ea42702b03add8f6d0c7387b8302aa7923bb2d4f2940fad668784',
                    'ab88723bf74108db94b94ad6a58aa38211cf31f247164ea1a496fc13432b85a2',
                    '31ae53af28922a52b8a777548635b628982710c7a42960bea03fdd773b2d4462',
                ],
            ],
            '/title/page/2/' => [
                'listing-page2.html',
                2,
                [
                    'PIYO-210', 'PIYO-213', 'PIYO-207', 'PIYO-212', 'PIYO-208', 'PIYO-206',
                    'PIYO-204', 'PIYO-201', 'PIYO-203', 'PIYO-200', 'PIYO-195', 'PIYO-199',
                    'PIYO-197', 'PIYO-196', 'PIYO-186', 'PIYO-193', 'PIYO-190', 'PIYO-191',
                    'PIYO-187', 'PIYO-185', 'PIYO-188', 'PIYO-184', 'PIYO-183', 'PIYO-182',
                    'PIYO-181', 'PIYO-179', 'PIYO-178', 'PIYO-176', 'PIYO-175', 'PIYO-174',
                ],
                [
                    '89de931a88b2b88af37dcf97becb4c72454d08dadc0f7e42452533ebe0cb4938',
                    '0b03be257f0aaa4fd6953a3591e7370208b5aeb30ebd56ded7ef31912296dc75',
                    '7a59b993dc3bf331eba7286faf0abc861efd1483ac4afd8ee94d53c025f20ea3',
                ],
            ],
        ] as $path => [$fixture, $expectedPage, $expectedCodes, $expectedFieldHashes]) {
            $result = (new Listing($this->clientWithFixture('hiyoko/' . $fixture)))
                ->execute(new CrawlRequestDto(url: self::BASE . $path));

            self::assertCount(30, $result->items);
            $codes = [];
            $titles = [];
            $detailUrls = [];
            $covers = [];
            foreach ($result->items as $item) {
                $movie = $item->meta['movie'];
                $codes[] = $movie['code'];
                $titles[] = $movie['title'];
                $detailUrls[] = $item->url;
                $covers[] = $movie['cover_url'];
                self::assertMatchesRegularExpression('#^' . self::BASE . '/title/piyo-\d+/$#', $item->url);
                self::assertMatchesRegularExpression('/^PIYO-\d+$/', $movie['external_id']);
                self::assertSame($movie['external_id'], $movie['code']);
                self::assertNotSame('', $movie['title']);
                self::assertMatchesRegularExpression('#^' . self::BASE . '/hiyokosys/wp-content/uploads/.+\.(?:jpg|png)$#i', $movie['cover_url']);
                self::assertSame([], $movie['performers']);
                self::assertSame('Hiyoko', $movie['metadata']['maker']);
            }

            self::assertSame($expectedCodes, $codes);
            self::assertSame($expectedFieldHashes[0], $this->fieldValuesHash($titles));
            self::assertSame($expectedFieldHashes[1], $this->fieldValuesHash($detailUrls));
            self::assertSame($expectedFieldHashes[2], $this->fieldValuesHash($covers));
            self::assertSame($expectedPage, $result->pagination->currentPage);
            self::assertSame(10, $result->pagination->lastPage);
            self::assertSame($expectedPage + 1, $result->pagination->nextPage);
            self::assertSame(self::BASE . '/title/page/' . ($expectedPage + 1) . '/', $result->pagination->nextUrl);
            self::assertTrue($result->pagination->hasNextPage);
        }
    }

    public function test_listing_skips_cards_without_links_and_keeps_valid_cards(): void
    {
        $html = <<<'HTML'
            <div id="second-block-in03">
                <ul class="list-07">
                    <li><span>Incomplete card</span></li>
                    <li>
                        <a href="/title/piyo-242/" title="PIYO-242">
                            <div>Hiyoko work</div>
                            <img src="/hiyokosys/wp-content/uploads/PIYO-242.jpg">
                        </a>
                    </li>
                </ul>
                <div class="wp-pagenavi">
                    <span class="current">1</span>
                    <span class="pages">1 of 10</span>
                    <a class="last" href="/title/page/10/">Last</a>
                    <a rel="next" href="/title/page/2/">Next</a>
                </div>
            </div>
            HTML;
        $result = (new Listing($this->clientWithHtml($html)))
            ->execute(new CrawlRequestDto(url: self::BASE . '/title/'));

        self::assertCount(1, $result->items);
        self::assertSame(self::BASE . '/title/piyo-242/', $result->items[0]->url);
        self::assertSame('PIYO-242', $result->items[0]->meta['movie']['code']);
        self::assertSame('Hiyoko work', $result->items[0]->meta['movie']['title']);
        self::assertSame(
            self::BASE . '/hiyokosys/wp-content/uploads/PIYO-242.jpg',
            $result->items[0]->meta['movie']['cover_url'],
        );
    }

    /** @param list<string> $values */
    private function fieldValuesHash(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
