<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\Shared\MakerCms;

use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Fields;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\SiteConfig;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Detail;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\Listing;
use JOOservices\CrawlerX\Adapters\Shared\MakerCms\Types\PerformerDetail;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Image completeness audit (JOOXCR-423): every content image on the real captures of the Maker CMS
 * family must reach the DTO. Counts were verified against the page markup (work slider images,
 * profile photo, one cover per listing card); related-works covers and site chrome are excluded.
 */
final class ImageAuditTest extends TestCase
{
    /** @return array<string, array{string, string, int, bool}> slug, work code, slider images, has sample-video poster */
    public static function details(): array
    {
        $rows = [
            ['attackers', 'ADN818', 11, true],
            ['attackers', 'SAME712', 11, true],
            ['befreebe', 'BF775', 1, true],
            ['bi', 'CJOD549', 16, true],
            ['bibian', 'BBAN612', 13, true],
            ['dasdas', 'DAZD317', 11, true],
            ['dasdas', 'DSOD137', 21, true],
            ['ebody', 'EBWH392', 11, true],
            ['fitch', 'DEAB010', 17, true],
            ['hajimekikaku', 'HJMO745', 17, true],
            ['hhh', 'HUNTC688', 16, true],
            ['hhh', 'ROYD377', 19, false],
            ['honnaka', 'HNMG003', 11, true],
            ['ideapocket', 'IDPC034', 11, true],
            ['ideapocket', 'IPOK042', 18, true],
            ['kawaiikawaii', 'KWBD444', 11, true],
            ['kirakira', 'KIBD249', 1, false],
            ['kirakira', 'KIBD359', 21, true],
            ['madonna', 'JUR920', 11, true],
            ['miman', 'MMXD035', 11, true],
            ['mko', 'MISM459', 11, true],
            ['moodyz', 'MIDA834', 11, true],
            ['muku', 'MUDR408', 11, true],
            ['muku', 'MUKC133', 18, true],
            ['mvg', 'MVBD200', 13, true],
            ['mvg', 'MVSD710', 20, true],
            ['nanpajapan', 'NPJB134', 11, true],
            ['nanpajapan', 'NPJS299', 11, true],
            ['opera', 'OPBD209', 1, true],
            ['oppai', 'PPPE459', 13, true],
            ['premiumbeauty', 'PBD529', 11, true],
            ['premiumbeauty', 'PRED896', 11, true],
            ['rookie', 'RKI773', 11, true],
            ['s1', 'SNOS524', 10, true],
            ['tameikegoro', 'MFYD231', 9, true],
            ['tosatsu', 'CLUB947', 13, true],
            ['tosatsu', 'STOL158', 19, true],
            ['vav', 'VVVD182', 1, false],
            ['vav', 'VVVD205', 1, false],
            ['wanz', 'WAAA716', 16, true],
        ];

        return self::keyed($rows);
    }

    /** @return array<string, array{string, string, int, int}> slug, actress id, profile photos, works with cover */
    public static function actresses(): array
    {
        $rows = [
            ['attackers', '347922', 1, 12],
            ['befreebe', '290053', 1, 12],
            ['bi', '138332', 1, 12],
            ['bibian', '851168', 1, 1],
            ['dasdas', '798158', 1, 12],
            ['ebody', '873771', 1, 1],
            ['fitch', '869733', 1, 1],
            ['honnaka', '769498', 1, 12],
            ['ideapocket', '876796', 1, 1],
            ['kawaiikawaii', '876969', 1, 1],
            ['kirakira', '33482', 1, 12],
            ['madonna', '871344', 1, 8],
            ['mko', '859341', 1, 4],
            ['moodyz', '713490', 1, 12],
            ['oppai', '872737', 1, 1],
            ['premiumbeauty', '800157', 1, 12],
            ['premiumbeauty', '844918', 1, 1],
            ['rookie', '851176', 1, 1],
            ['s1', '22486', 1, 12],
            ['s1', '874653', 1, 1],
            ['tameikegoro', '876448', 1, 1],
            ['wanz', '723349', 1, 12],
        ];

        return self::keyed($rows);
    }

    /** @return array<string, array{string, string, int}> slug, listing key, cards = items = covers */
    public static function listings(): array
    {
        $rows = [
            ['attackers', '2026-12-01', 22],
            ['attackers', 'genre-128', 30],
            ['befreebe', '2026-12-01', 2],
            ['bi', '2026-11-24', 7],
            ['bibian', '2026-11-10', 5],
            ['bibian', 'genre-352', 6],
            ['dasdas', '2026-11-24', 13],
            ['dasdas', 'genre-335', 17],
            ['ebody', '2026-11-17', 11],
            ['fitch', '2026-12-01', 6],
            ['hajimekikaku', '2026-11-17', 5],
            ['hhh', '2026-11-10', 20],
            ['hhh', '2026-11-24', 17],
            ['honnaka', '2026-11-24', 6],
            ['ideapocket', '2026-12-01', 12],
            ['ideapocket', 'genre-315', 30],
            ['kawaiikawaii', '2026-11-03', 13],
            ['kawaiikawaii', '2026-12-01', 12],
            ['kirakira', '2026-11-17', 2],
            ['kirakira', 'genre-128', 30],
            ['madonna', '2026-11-10', 30],
            ['madonna', '2026-12-01', 1],
            ['miman', '2024-01-02', 1],
            ['mko', '2026-11-24', 5],
            ['moodyz', '2026-11-03', 26],
            ['moodyz', '2026-12-01', 22],
            ['muku', '2026-11-17', 14],
            ['mvg', '2026-11-17', 4],
            ['mvg', 'genre-215', 30],
            ['nanpajapan', '2026-12-01', 6],
            ['nanpajapan', 'genre-333', 9],
            ['opera', '2023-08-22', 1],
            ['oppai', '2026-11-17', 7],
            ['oppai', 'genre-315', 30],
            ['premiumbeauty', '2026-11-17', 8],
            ['premiumbeauty', 'genre-218', 30],
            ['rookie', '2026-10-13', 6],
            ['rookie', '2026-11-10', 3],
            ['s1', '2026-11-24', 23],
            ['tameikegoro', '2026-11-17', 15],
            ['tosatsu', '2026-12-01', 3],
            ['vav', '2021-02-01', 1],
            ['vav', '2022-05-03', 1],
            ['wanz', '2026-12-01', 11],
        ];

        return self::keyed($rows);
    }

    #[DataProvider('details')]
    public function test_detail_extracts_every_slider_image(string $slug, string $code, int $images, bool $poster): void
    {
        $config = $this->config($slug);
        $movie = (new Detail($this->clientWithFixture("{$slug}/detail-{$code}.html"), $config))->execute(new CrawlRequestDto(url: $config->baseUrl . '/works/detail/' . $code))->meta['movie'];

        self::assertCount($images, $movie['screenshots']);
        self::assertSame($movie['cover_url'], $movie['screenshots'][0]['url']);
        self::assertCount($images, array_unique(array_column($movie['screenshots'], 'url')));
        foreach ($movie['screenshots'] as $shot) {
            self::assertStringStartsWith('https://', $shot['url']);
            self::assertStringNotContainsString('?', $shot['url']);
        }
        self::assertSame($poster, $movie['metadata']['sample_video_poster_url'] !== null);
    }

    #[DataProvider('actresses')]
    public function test_performer_detail_extracts_profile_photo_and_work_covers(string $slug, string $id, int $photos, int $works): void
    {
        $config = $this->config($slug);
        $performer = (new PerformerDetail($this->clientWithFixture("{$slug}/actress-{$id}.html"), $config))->execute(new CrawlRequestDto(url: $config->baseUrl . '/actress/detail/' . $id))->meta['performer'];

        self::assertCount($photos, $performer['metadata']['images']);
        self::assertSame($performer['metadata']['images'][0], $performer['profile_image_url']);
        self::assertStringContainsString('/actress_main/' . $id . '/', $performer['profile_image_url']);
        self::assertCount($works, $performer['metadata']['works']);
        self::assertCount($works, array_filter(array_column($performer['metadata']['works'], 'cover_url')));
    }

    #[DataProvider('listings')]
    public function test_listing_card_has_a_cover(string $slug, string $key, int $cards): void
    {
        $config = $this->config($slug);
        $path = str_starts_with($key, 'genre-') ? '/works/list/genre/' . substr($key, 6) : '/works/list/date/' . $key;
        $result = (new Listing($this->clientWithFixture("{$slug}/list-{$key}.html"), $config))->execute(new CrawlRequestDto(url: $config->baseUrl . $path));

        self::assertCount($cards, $result->items);
        foreach ($result->items as $item) {
            self::assertStringStartsWith('https://', $item->meta['movie']['cover_url']);
        }
    }

    public function test_opera_actress_without_photo_is_rejected_not_guessed(): void
    {
        $this->expectException(CrawlParseException::class);
        $config = $this->config('opera');
        (new PerformerDetail($this->clientWithFixture('opera/actress-812985.html'), $config))->execute(new CrawlRequestDto(url: $config->baseUrl . '/actress/detail/812985'));
    }

    public function test_image_reads_lazy_attributes_and_skips_inline_placeholders(): void
    {
        $fields = new Fields(new SiteConfig(baseUrl: 'https://example.test', maker: 'X'));
        $dom = new Crawler(<<<'HTML'
            <div>
              <p class="a"><img src="data:image/gif;base64,R0lGOD" data-original="/o.jpg"></p>
              <p class="b"><img src="data:image/gif;base64,R0lGOD" data-lazy-src="//cdn.test/l.jpg"></p>
              <p class="c"><img src="data:image/gif;base64,R0lGOD" srcset="/s1.jpg 320w, /s3.jpg 1280w, /s2.jpg 640w"></p>
              <p class="d"><img data-src="/d.jpg" src="/placeholder.gif"></p>
              <p class="f"><img srcset="data:image/gif;base64,R0lGOD 1x"></p>
              <p class="g"><img srcset="data:image/gif;base64,R0lGOD 1x, /s5.jpg 2x"></p>
              <p class="e"><img src="data:image/gif;base64,R0lGOD"></p>
            </div>
            HTML);

        self::assertSame('https://example.test/o.jpg', $fields->image($dom, '.a img'));
        self::assertSame('https://cdn.test/l.jpg', $fields->image($dom, '.b img'));
        self::assertSame('https://example.test/s3.jpg', $fields->image($dom, '.c img'));
        self::assertSame('https://example.test/d.jpg', $fields->image($dom, '.d img'));
        self::assertNull($fields->image($dom, '.e img'));
        self::assertNull($fields->image($dom, '.f img'));
        self::assertSame('https://example.test/s5.jpg', $fields->image($dom, '.g img'));
        self::assertNull($fields->image($dom, '.missing img'));
    }

    private function config(string $slug): SiteConfig
    {
        $site = 'JOOservices\\CrawlerX\\Adapters\\' . self::ADAPTERS[$slug] . '\\Site';

        return $site::config();
    }

    /** @param list<array<int, mixed>> $rows
     * @return array<string, array<int, mixed>>
     */
    private static function keyed(array $rows): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$row[0] . ' ' . $row[1]] = $row;
        }

        return $keyed;
    }

    private const ADAPTERS = [
        'attackers' => 'Attackers',
        'befreebe' => 'BeFreeBe',
        'bi' => 'Bi',
        'bibian' => 'Bibian',
        'dasdas' => 'Dasdas',
        'ebody' => 'Ebody',
        'fitch' => 'Fitch',
        'hajimekikaku' => 'Hajimekikaku',
        'hhh' => 'Hhh',
        'honnaka' => 'Honnaka',
        'ideapocket' => 'Ideapocket',
        'kawaiikawaii' => 'KawaiiKawaii',
        'kirakira' => 'Kirakira',
        'madonna' => 'Madonna',
        'miman' => 'Miman',
        'mko' => 'Mko',
        'moodyz' => 'Moodyz',
        'muku' => 'Muku',
        'mvg' => 'Mvg',
        'nanpajapan' => 'Nanpajapan',
        'opera' => 'Opera',
        'oppai' => 'Oppai',
        'premiumbeauty' => 'PremiumBeauty',
        'rookie' => 'Rookie',
        's1' => 'S1',
        'tameikegoro' => 'Tameikegoro',
        'tosatsu' => 'Tosatsu',
        'vav' => 'Vav',
        'wanz' => 'Wanz',
    ];

}
