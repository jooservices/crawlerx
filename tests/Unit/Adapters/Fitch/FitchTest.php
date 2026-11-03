<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Fitch;

use JOOservices\CrawlerX\Adapters\Fitch\FitchCrawler;
use JOOservices\CrawlerX\Adapters\Fitch\Site;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Registry\AdapterRegistry;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Services\ClientFactory;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

class FitchTest extends TestCase
{
    private const string BASE = 'https://fitch-av.com';

    /** @return iterable<string, array{string, string, class-string}> */
    public static function captures(): iterable
    {
        yield 'debut' => ['detail-DEAB010.html', '/works/detail/DEAB010', Detail::class];
        yield 'second release' => ['detail-FPRE247.html', '/works/detail/FPRE247', Detail::class];
        yield 'date cards' => ['list-2026-12-01.html', '/works/list/date/2026-12-01', Listing::class];
        yield 'release sections' => ['list-release.html', '/works/list/release', Listing::class];
        yield 'reserve cards' => ['list-reserve.html', '/works/list/reserve', Listing::class];
        yield 'keyword cards' => ['list-search.html', '/search/list?keyword=DEAB010', Listing::class];
        yield 'label cards' => ['list-label-10123.html', '/works/list/label/10123', Listing::class];
        yield 'empty bio' => ['actress-876895.html', '/actress/detail/876895', PerformerDetail::class];
        yield 'measurements' => ['actress-869733.html', '/actress/detail/869733', PerformerDetail::class];
    }

    #[DataProvider('captures')]
    public function test_every_captured_field_and_array(string $fixture, string $path, string $type): void
    {
        $result = (new $type($this->clientWithHtml($this->capture($fixture)), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . $path));
        $expected = json_decode((string) file_get_contents(__DIR__ . '/../../../Expectations/fitch/' . $fixture . '.json'), true, 512, JSON_THROW_ON_ERROR);
        $actual = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFields($expected, $actual, $fixture);
    }

    public function test_all_labelled_detail_rows_are_retained_without_recommendations(): void
    {
        $result = (new Detail($this->clientWithFixture('fitch/detail-DEAB010.html'), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/detail/DEAB010'));
        $movie = $result->meta['movie'];
        $dom = new Crawler($this->loadFixture('fitch/detail-DEAB010.html'));
        $rows = [];
        foreach ($dom->filter('.p-workPage__table > .item') as $element) {
            $row = new Crawler($element);
            $rows[$row->filter('.th')->text()] = $row->text();
        }
        self::assertSame(['女優', '発売日', 'シリーズ', 'レーベル', 'ジャンル', '監督', '品番', '収録時間', '価格'], array_keys($rows));
        foreach ($rows as $label => $text) {
            self::assertSame($text, $movie['metadata']['source_fields'][$label], $label);
        }
        self::assertSame('電影美女', $movie['metadata']['label'][0]['name']);
        self::assertSame(150, $movie['duration']);
        self::assertSame('2026-12-01', $movie['date']);
        self::assertCount(17, $movie['screenshots']);
        self::assertCount(1, $movie['performers']);
        self::assertSame('DEAB010', $movie['code']);
        self::assertStringNotContainsString('FPRE247', json_encode($movie, JSON_THROW_ON_ERROR));
    }

    public function test_date_index_retains_group_fields_and_pagination(): void
    {
        $config = new SiteConfig(baseUrl: self::BASE, maker: 'Fitch', dateGapSeconds: 0);
        $client = $this->clientWithMappedResponses([
            self::BASE . '/works/date' => ['body' => $this->capture('dates.html')],
            self::BASE . '/works/list/date/2026-12-01' => ['body' => $this->loadFixture('fitch/list-2026-12-01.html')],
        ]);
        $result = (new Listing($client, $config))->execute(new CrawlRequestDto(url: self::BASE . '/works/date'));
        self::assertCount(6, $result->items);
        $group = $result->items[0]->meta['movie']['metadata']['release_group'];
        self::assertSame('2026-12-01', $group['date']);
        self::assertSame('2026-12-01', $group['external_id']);
        self::assertSame(self::BASE . '/works/list/date/2026-12-01', $group['url']);
        self::assertSame(6, $group['count']);
        self::assertStringContainsString('2026年12月1日', $group['name']);
        self::assertSame(1, $result->pagination->currentPage);
        self::assertSame(2, $result->pagination->nextPage);
        self::assertSame(self::BASE . '/works/date?page=2', $result->pagination->nextUrl);
        self::assertTrue($result->pagination->hasNextPage);
        $dom = new Crawler($this->capture('dates.html'));
        self::assertSame($dom->filter('.p-accordion .genre a[href*="/works/list/date/"]')->count(), $result->pagination->lastPage);
    }

    public function test_release_sections_keep_their_own_dates_and_counts(): void
    {
        $result = (new Listing($this->clientWithHtml($this->capture('list-release.html')), Site::config()))->execute(new CrawlRequestDto(url: self::BASE . '/works/list/release'));
        self::assertCount(15, $result->items);
        foreach ([0 => ['2026-11-03', 7], 7 => ['2026-10-20', 4], 11 => ['2026-10-06', 4]] as $index => [$date, $count]) {
            $movie = $result->items[$index]->meta['movie'];
            self::assertSame($date, $movie['date']);
            self::assertSame($date, $movie['metadata']['release_group']['date']);
            self::assertSame($count, $movie['metadata']['release_group']['count']);
            self::assertSame(self::BASE . '/works/list/date/' . $date, $movie['metadata']['release_group']['url']);
            self::assertSame($movie['metadata']['release_group']['name'], $movie['metadata']['search_key']);
        }
    }

    public function test_registry_and_url_inference(): void
    {
        $manifests = new FileAdapterManifestRegistry();
        $registry = new AdapterRegistry(new ClientFactory(), $manifests);
        $registry->registerFromManifests($manifests);
        $crawler = $registry->resolve('fitch');
        self::assertInstanceOf(FitchCrawler::class, $crawler);
        self::assertSame('fitch', $crawler->name());
        foreach ([
            '/works/date' => CrawlType::Listing,
            '/works/list/release' => CrawlType::Listing,
            '/works/list/reserve' => CrawlType::Listing,
            '/search/list?keyword=DEAB010' => CrawlType::Listing,
            '/works/list/date/2026-12-01' => CrawlType::Listing,
            '/works/list/genre/315' => CrawlType::Listing,
            '/works/list/label/10123' => CrawlType::Listing,
            '/works/list/series/1' => CrawlType::Listing,
            '/works/detail/DEAB010' => CrawlType::Detail,
            '/actress/detail/869733' => CrawlType::PerformerDetail,
        ] as $path => $type) {
            $result = $crawler->detectUrl(self::BASE . $path);
            self::assertTrue($result->matched, $path);
            self::assertSame($type, $result->crawlType, $path);
        }
        self::assertFalse($crawler->detectUrl(self::BASE . '/works/list/unknown/1')->matched);
    }

    private function capture(string $fixture): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/Fixtures/fitch/' . $fixture;
        if (! is_file($path)) {
            $path = $root . '/FamilyFixtures/fitch/' . $fixture;
        }
        self::assertFileExists($path);
        $metadata = json_decode((string) file_get_contents($path . '.meta.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(hash_file('sha256', $path), $metadata['content_hash']);
        self::assertSame(200, $metadata['http_status']);

        return (string) file_get_contents($path);
    }

    /** @param array<string|int, mixed> $expected
     * @param array<string|int, mixed> $actual
     */
    private function assertFields(array $expected, array $actual, string $path): void
    {
        self::assertSame(array_keys($expected), array_keys($actual), $path . ' fields and array lengths');
        foreach ($expected as $key => $value) {
            if (is_array($value)) {
                self::assertIsArray($actual[$key], $path . '.' . $key);
                $this->assertFields($value, $actual[$key], $path . '.' . $key);
                continue;
            }
            self::assertSame($value, $actual[$key], $path . '.' . $key);
        }
    }
}
