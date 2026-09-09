<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Tests\CrawlerXTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NewProviderCrawlTest extends CrawlerXTestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function movieProviderFixtures(): iterable
    {
        yield '10musume gallery' => ['https://www.10musume.com/movies/090826_01/', '10musume/detail-090826_01.html'];
        yield 'pacopacomama gallery' => ['https://www.pacopacomama.com/movies/090826_100/', 'pacopacomama/detail-090826_100.html'];
        yield 'muramura cover' => ['https://www.muramura.tv/movies/020426_1218/', 'muramura/detail-020426_1218.html'];
        yield 'kin8tengoku cover' => ['https://www.kin8tengoku.com/movie/4245', 'kin8tengoku/detail-4245.html'];
        yield 'moodyz cover' => ['https://moodyz.com/works/detail/MIDA812', 'moodyz/detail-mida812.html'];
        yield 'ideapocket cover' => ['https://ideapocket.com/works/detail/IPZZ972', 'ideapocket/detail-ipzz972.html'];
        yield 's1 cover' => ['https://s1s1s1.com/works/detail/SNOS444', 's1/detail-snos444.html'];
        yield 'madonna cover' => ['https://madonna-av.com/works/detail/JUVR281', 'madonna/detail-juvr281.html'];
    }

    #[DataProvider('movieProviderFixtures')]
    public function test_movie_provider_detail_exposes_cover_or_gallery(string $url, string $fixture): void
    {
        $this->respondWithFixture('GET', $url, $fixture);

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('movie', $result->entityType);
        self::assertNotEmpty($result->meta['movie']['cover_url'] ?? $result->meta['movie']['screenshots'] ?? []);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function performerProviderFixtures(): iterable
    {
        yield 'T-Powers' => ['https://www.t-powers.co.jp/talent/aika/', 'tpowers/detail-aika.html'];
        yield 'Mine\'s' => ['https://mines-pro.jp/model/10953', 'mines/detail-eimi-fukada.html'];
        yield 'Bstar' => ['https://bstar-pro.com/model.html?mid=410', 'bstar/detail-410.html'];
        yield 'SOFT ON DEMAND' => ['https://www.sod.co.jp/actress/matsunagaakari', 'sod/performer-detail-matsunagaakari.html'];
    }

    #[DataProvider('performerProviderFixtures')]
    public function test_performer_provider_detail_exposes_a_profile(string $url, string $fixture): void
    {
        $this->respondWithFixture('GET', $url, $fixture);

        $result = CrawlerX::url($url)->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('performer', $result->entityType);
        self::assertNotSame('', trim((string) ($result->meta['performer']['name'] ?? '')));
        self::assertNotSame('', trim((string) ($result->meta['performer']['external_id'] ?? '')));
    }
}
