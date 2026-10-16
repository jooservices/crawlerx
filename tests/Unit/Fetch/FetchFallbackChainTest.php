<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use PHPUnit\Framework\TestCase;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\Guard\TransientRetry;

final class FetchFallbackChainTest extends TestCase
{
    public function test_returns_first_successful_handler(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, false),
            FetchMethod::Playwright->value => $this->handler(FetchMethod::Playwright, true, '<html>ok</html>'),
        ]);

        $result = $chain->fetch(
            'https://example.test',
            $this->profile(),
            [FetchMethod::Http, FetchMethod::Playwright],
        );

        self::assertTrue($result->ok);
        self::assertSame(FetchMethod::Playwright, $result->methodUsed);
        self::assertSame('<html>ok</html>', $result->body);
        self::assertCount(2, $result->attempts);
    }

    public function test_throws_when_every_handler_fails(): void
    {
        $chain = new FetchFallbackChain([
            FetchMethod::Http->value => $this->handler(FetchMethod::Http, false),
        ]);

        $this->expectException(CrawlBlockedException::class);

        $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http]);
    }

    public function test_transient_retries_can_be_disabled_for_the_matrix_cooldown_retry(): void
    {
        $handler = new class implements FetchMethodHandler {
            public int $calls = 0;

            public function supports(FetchMethod $method): bool
            {
                return $method === FetchMethod::Http;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                ++$this->calls;

                return new FetchResultDto(
                    ok: false,
                    body: '',
                    status: 0,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: false,
                    error: TransientRetry::NETWORK_ERROR,
                );
            }
        };
        $sleeps = 0;
        $chain = new FetchFallbackChain(
            [FetchMethod::Http->value => $handler],
            retrySleeper: static function (int $microseconds) use (&$sleeps): void {
                ++$sleeps;
            },
            maxTransientRetries: 0,
        );

        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http]);
            self::fail('Expected the final network failure.');
        } catch (CrawlFetchException $exception) {
            self::assertSame(CrawlErrorCode::Network, $exception->errorCode);
        }

        self::assertSame(1, $handler->calls);
        self::assertSame(0, $sleeps);
    }

    private function handler(FetchMethod $supports, bool $ok, string $body = ''): FetchMethodHandler
    {
        return new class ($supports, $ok, $body) implements FetchMethodHandler {
            public function __construct(
                private FetchMethod $supports,
                private bool $ok,
                private string $body,
            ) {
            }

            public function supports(FetchMethod $method): bool
            {
                return $method === $this->supports;
            }

            public function fetch(
                string $url,
                SiteProfileDto $profile,
                FetchMethod $method,
                ?CrawlOptionsDto $options = null,
            ): FetchResultDto {
                return new FetchResultDto(
                    ok: $this->ok,
                    body: $this->body,
                    status: $this->ok ? 200 : 403,
                    methodUsed: $method,
                    elapsedMs: 1,
                    challengeDetected: ! $this->ok,
                    finalUrl: $url,
                    error: $this->ok ? null : 'blocked',
                );
            }
        };
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(
            slug: 'demo',
            displayName: 'Demo',
            baseUrl: 'https://example.test',
            fetchProfile: FetchProfile::Adaptive,
            fetchChain: [FetchMethod::Http, FetchMethod::Playwright],
            http: new HttpProfileDto(),
        );
    }
}
