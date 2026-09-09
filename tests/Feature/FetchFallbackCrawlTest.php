<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Feature;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchChainDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Tests\TestCase;

final class FetchFallbackCrawlTest extends TestCase
{
    protected function tearDown(): void
    {
        CrawlerXFactory::reset();
        parent::tearDown();
    }

    public function test_browser_profile_falls_back_and_seeds_adapter(): void
    {
        $body = $this->loadFixture('jable/detail-fjin-091.html');
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => $method === FetchMethod::Playwright
            ? self::failure($method, challenge: true)
            : self::success($method, $body, $url));
        $this->useHandler($handler, FetchMethod::browserChain());

        $result = CrawlerX::url('https://en.jable.tv/videos/fjin-091/')->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame('FJIN-091', $result->meta['movie']['code']);
        self::assertSame([FetchMethod::Playwright, FetchMethod::PlaywrightStealth], $handler->attempts);
    }

    public function test_explicit_method_without_fallback_uses_only_requested_handler(): void
    {
        $body = $this->loadFixture('onejav/listing-page-1.html');
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => self::success($method, $body, $url));
        $this->useHandler($handler, [FetchMethod::ChromeStealth]);

        $result = CrawlerX::url('https://onejav.com/new')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(method: FetchMethod::ChromeStealth, noFallback: true),
        ))->crawl();

        self::assertInstanceOf(CrawlListResultDto::class, $result);
        self::assertNotEmpty($result->items);
        self::assertSame([FetchMethod::ChromeStealth], $handler->attempts);
    }

    public function test_explicit_chain_preserves_order(): void
    {
        $body = $this->loadFixture('onejav/detail-sample-1.html');
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => $method === FetchMethod::CurlImpersonate
            ? self::failure($method)
            : self::success($method, $body, $url));
        $methods = [FetchMethod::CurlImpersonate, FetchMethod::Flaresolverr];
        $this->useHandler($handler, $methods);

        $result = CrawlerX::url('https://onejav.com/torrent/ymds282')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(chain: new FetchChainDto($methods)),
        ))->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame($methods, $handler->attempts);
    }

    public function test_exhausted_chain_maps_to_blocked_outcome_with_attempts(): void
    {
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => self::failure($method, challenge: true));
        $methods = [FetchMethod::Playwright, FetchMethod::Flaresolverr];
        $this->useHandler($handler, $methods);

        $outcome = CrawlerX::url('https://en.jable.tv/new-release/')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(chain: new FetchChainDto($methods)),
        ))->tryCrawl();

        self::assertTrue($outcome->failed());
        self::assertSame(CrawlErrorCode::Blocked, $outcome->error?->code);
        self::assertCount(2, $outcome->error?->fetch?->attempts ?? []);
    }

    public function test_named_profile_and_method_start_inside_profile_chain_through_facade(): void
    {
        $body = $this->loadFixture('jable/detail-fjin-091.html');
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => self::success($method, $body, $url));
        $this->useHandler($handler, [FetchMethod::ChromeStealth]);

        $result = CrawlerX::url('https://en.jable.tv/videos/fjin-091/')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(profile: FetchProfile::Adaptive, method: FetchMethod::ChromeStealth),
        ))->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame([FetchMethod::ChromeStealth], $handler->attempts);
    }

    public function test_method_missing_from_site_chain_is_prepended_through_facade(): void
    {
        $body = $this->loadFixture('jable/detail-fjin-091.html');
        $handler = $this->handler(static fn(FetchMethod $method, string $url): FetchResultDto => self::success($method, $body, $url));
        $this->useHandler($handler, [FetchMethod::Http]);

        $result = CrawlerX::url('https://en.jable.tv/videos/fjin-091/')->options(new CrawlOptionsDto(
            fetch: new FetchOptionsDto(method: FetchMethod::Http),
        ))->crawl();

        self::assertInstanceOf(CrawlItemResultDto::class, $result);
        self::assertSame([FetchMethod::Http], $handler->attempts);
    }

    private function handler(callable $callback): FetchMethodHandler
    {
        return new class ($callback) implements FetchMethodHandler {
            /** @var list<FetchMethod> */
            public array $attempts = [];

            public function __construct(private readonly mixed $callback)
            {
            }

            public function supports(FetchMethod $method): bool
            {
                return true;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                $this->attempts[] = $method;
                /** @var callable(FetchMethod, string): FetchResultDto $callback */
                $callback = $this->callback;

                return $callback($method, $url);
            }
        };
    }

    /** @param list<FetchMethod> $methods */
    private function useHandler(FetchMethodHandler $handler, array $methods): void
    {
        $handlers = [];
        foreach ($methods as $method) {
            $handlers[$method->value] = $handler;
        }
        CrawlerXFactory::useFetchChain(new FetchFallbackChain($handlers));
    }

    private static function success(FetchMethod $method, string $body, string $url): FetchResultDto
    {
        return new FetchResultDto(
            ok: true,
            body: $body,
            status: 200,
            methodUsed: $method,
            elapsedMs: 1,
            challengeDetected: false,
            finalUrl: $url,
        );
    }

    private static function failure(FetchMethod $method, bool $challenge = false): FetchResultDto
    {
        return new FetchResultDto(
            ok: false,
            body: '',
            status: $challenge ? 403 : 0,
            methodUsed: $method,
            elapsedMs: 1,
            challengeDetected: $challenge,
            error: 'failed',
        );
    }
}
