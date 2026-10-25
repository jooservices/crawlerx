<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Contracts\FetchMethodHandler;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Exceptions\CrawlFetchException;
use JOOservices\CrawlerX\Fetch\FetchFallbackChain;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTimeImmutable;

final class FetchAttemptTest extends TestCase
{
    public function test_challenge_and_flare_success_have_ordered_diagnostics(): void
    {
        $handler = $this->handler();
        $result = (new FetchFallbackChain(['http' => $handler, 'flaresolverr' => $handler]))
            ->fetch('https://example.test', $this->profile(), [FetchMethod::Http, FetchMethod::Flaresolverr]);

        self::assertSame(['http', 'flaresolverr'], array_column($result->attempts, 'method'));
        self::assertSame(['error', 'ok'], array_column($result->attempts, 'outcome'));
        self::assertSame([403, 200], array_column($result->attempts, 'http_status'));
        self::assertSame('challenge', $result->attempts[0]['cf_mitigated']);
        self::assertNull($result->attempts[1]['cf_mitigated']);
        self::assertNotEmpty($result->attempts[0]['started_at']);
        self::assertInstanceOf(DateTimeImmutable::class, new DateTimeImmutable($result->attempts[0]['started_at']));
        self::assertSame('challenge', $result->attempts[0]['error_code']);
        self::assertNull($result->attempts[1]['error_code']);
        self::assertGreaterThanOrEqual(0, $result->attempts[0]['duration_ms']);
        self::assertSame($result->attempts, $result->toMeta()->attempts);
    }

    public function test_budget_skip_is_returned_on_exception(): void
    {
        $now = 0;
        $handler = $this->handler(static function () use (&$now): void {
            $now = 2_000_000_000;
        });
        $chain = new FetchFallbackChain(['http' => $handler], budgetClock: static function () use (&$now): int {
            return $now;
        });
        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http, FetchMethod::Playwright], new CrawlOptionsDto(fetch: new FetchOptionsDto(deadlineSeconds: 1)));
            self::fail('Expected timeout');
        } catch (CrawlFetchException $exception) {
            $attempt = $exception->fetch?->attempts[1];
            self::assertSame('skipped', $attempt['outcome']);
            self::assertSame('fetch budget exhausted', $attempt['error']);
            self::assertNull($attempt['http_status']);
            self::assertFalse($attempt['ok']);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function errors(): iterable
    {
        yield 'URL' => ['failed https://example.test?token=private', 'failed [url]'];
        yield 'HTML' => ['<html>private body</html>', 'HTML error omitted'];
        yield 'Unicode' => [str_repeat('あ', 600), str_repeat('あ', 500)];
        yield 'long' => [str_repeat('x', 600), str_repeat('x', 500)];
    }

    #[DataProvider('errors')]
    public function test_errors_are_short_and_do_not_expose_urls_or_html(string $error, string $expected): void
    {
        $chain = new FetchFallbackChain(['http' => $this->handler(error: $error)]);
        try {
            $chain->fetch('https://example.test', $this->profile(), [FetchMethod::Http]);
            self::fail('Expected challenge failure');
        } catch (CrawlFetchException $exception) {
            self::assertSame($expected, $exception->fetch?->attempts[0]['error']);
        }
    }

    private function handler(?\Closure $after = null, ?string $error = null): FetchMethodHandler
    {
        return new class ($after, $error) implements FetchMethodHandler {
            public function __construct(private readonly ?\Closure $after, private readonly ?string $error)
            {
            }
            public function supports(FetchMethod $method): bool
            {
                return true;
            }
            public function fetch(string $url, SiteProfileDto $profile, FetchMethod $method, ?CrawlOptionsDto $options = null): FetchResultDto
            {
                ($this->after ?? static function (): void {
                })();
                $ok = $method === FetchMethod::Flaresolverr;
                return new FetchResultDto(ok: $ok, body: '<html>page</html>', status: $ok ? 200 : 403, methodUsed: $method, elapsedMs: 3, challengeDetected: !$ok, headers: $ok ? [] : ['CF-Mitigated' => ['challenge']], error: $this->error);
            }
        };
    }

    private function profile(): SiteProfileDto
    {
        return new SiteProfileDto(slug: 'sample', displayName: 'Sample', baseUrl: 'https://example.test', fetchProfile: FetchProfile::Adaptive, fetchChain: [], http: new HttpProfileDto());
    }
}
