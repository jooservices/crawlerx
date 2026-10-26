<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Registry;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use JOOservices\CrawlerX\Tests\Support\ManifestFixtureCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceReliabilityTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function challengeSites(): iterable
    {
        yield 'Jable' => ['jable'];
        yield 'JAVLibrary' => ['javlibrary'];
        yield 'OneJav' => ['onejav'];
        yield 'MissAV' => ['missav'];
    }

    #[DataProvider('challengeSites')]
    public function test_manifest_uses_the_working_solver_before_a_slow_browser(string $slug): void
    {
        $manifest = (new FileAdapterManifestRegistry())->get($slug);
        self::assertNotNull($manifest);
        $profile = SiteProfileDto::fromManifest($manifest);
        self::assertSame([FetchMethod::Http, FetchMethod::Flaresolverr, FetchMethod::Playwright], $profile->fetchChain);
        $sample = $manifest->fixtureSamples[0];
        $body = (string) file_get_contents(ManifestFixtureCatalog::fixturePath($slug, $sample->name));
        $handler = new class ($body) implements FetchMethodHandler {
            public function __construct(private readonly string $body)
            {
            }
            public function supports(FetchMethod $method): bool
            {
                return true;
            }
            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                $ok = $method === FetchMethod::Flaresolverr;
                return new FetchResultDto(ok: $ok, body: $ok ? $this->body : '', status: $ok ? 200 : 403, methodUsed: $method, elapsedMs: 1, challengeDetected: ! $ok);
            }
        };
        $result = (new FetchFallbackChain(['http' => $handler, 'flaresolverr' => $handler]))->fetch($sample->url, $profile, $profile->fetchChain, type: CrawlType::Listing);
        self::assertTrue($result->ok);
        self::assertSame(['http', 'flaresolverr'], array_column($result->attempts, 'method'));
    }

    public function test_duga_waits_for_product_cards_and_its_own_title(): void
    {
        $manifest = (new FileAdapterManifestRegistry())->get('duga');
        self::assertNotNull($manifest);
        $profile = SiteProfileDto::fromManifest($manifest);
        $listing = (string) file_get_contents(ManifestFixtureCatalog::fixturePath('duga', 'listing-ranking.html'));
        self::assertTrue(ChallengeDetector::isUsableBody($listing, 200, $profile->readyMarkersFor(CrawlType::Listing)));
        self::assertFalse(ChallengeDetector::isUsableBody('<html><nav class="sidemenu">Menu</nav></html>', 200, $profile->readyMarkersFor(CrawlType::Listing)));
        self::assertFalse(ChallengeDetector::isUsableBody('<html><a href="/ppv/related/">Related product</a></html>', 200, $profile->readyMarkersFor(CrawlType::Detail)));
        $detail = (string) file_get_contents(ManifestFixtureCatalog::fixturePath('duga', 'detail.html'));
        self::assertTrue(ChallengeDetector::isUsableBody($detail, 200, $profile->readyMarkersFor(CrawlType::Detail)));
    }

    public function test_slow_challenge_sources_try_flaresolverr_before_the_browser(): void
    {
        $registry = new FileAdapterManifestRegistry();

        foreach (['onejav', 'missav'] as $slug) {
            $manifest = $registry->get($slug);
            self::assertNotNull($manifest);
            $profile = SiteProfileDto::fromManifest($manifest);
            self::assertSame([FetchMethod::Http, FetchMethod::Flaresolverr, FetchMethod::Playwright], $profile->fetchChain);
        }
    }
}
