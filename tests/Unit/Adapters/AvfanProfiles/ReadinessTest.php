<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvfanProfiles;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;
use PHPUnit\Framework\TestCase;

class ReadinessTest extends TestCase
{
    public function test_removed_profile_stops_before_starting_a_browser(): void
    {
        $body = (string) file_get_contents(dirname(__DIR__, 3) . '/Fixtures/AvfanProfiles/detail-removed.txt');
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
                return new FetchResultDto(ok: true, body: $this->body, status: 200, methodUsed: $method, elapsedMs: 1, challengeDetected: false);
            }
        };
        $manifest = (new FileAdapterManifestRegistry())->get('avfan_profiles');
        self::assertNotNull($manifest);
        try {
            (new FetchFallbackChain(['http' => $handler]))->fetch($manifest->baseUrl . '/actress/removed.html', SiteProfileDto::fromManifest($manifest), [FetchMethod::Http, FetchMethod::Playwright], type: CrawlType::PerformerDetail);
            self::fail('Expected a terminal soft 404.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::NotFound, $exception->errorCode);
            self::assertFalse($exception->retryable);
            self::assertCount(1, $exception->fetch?->attempts ?? []);
            self::assertSame('http', $exception->fetch?->attempts[0]['method']);
        }
    }
}
