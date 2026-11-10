<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleSelector;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Request;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckRequestLimitException.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckSampleSelector.php';

final class LiveCheckReportRunnerProfileTest extends TestCase
{
    public function test_definite_failure_categories_survive_http_only_and_fallback_enabled_profiles(): void
    {
        $cases = [
            [CrawlErrorCode::ParseFailed, 'parse-error'],
            [CrawlErrorCode::Unknown, 'parse-error'],
            [CrawlErrorCode::Network, 'network'],
            [CrawlErrorCode::Timeout, 'network'],
            [CrawlErrorCode::NotFound, 'empty'],
            [CrawlErrorCode::Gone, 'empty'],
            [CrawlErrorCode::Blocked, 'blocked'],
            [CrawlErrorCode::RateLimited, 'blocked'],
            [CrawlErrorCode::Challenge, 'blocked'],
        ];

        foreach (['http_only', 'adaptive', 'browser_likely'] as $profile) {
            foreach ($cases as [$code, $expectedStatus]) {
                $result = $this->runSample(
                    CrawlOutcomeDto::failure(new CrawlErrorDto($code, 'synthetic failure')),
                    $profile,
                );
                $fallbackRequired = $profile !== 'http_only';
                $expected = $code === CrawlErrorCode::Challenge && $fallbackRequired
                    ? 'not-checked'
                    : $expectedStatus;

                self::assertSame($expected, $result['status'], $code->value);
            }
        }
    }

    public function test_transport_and_unexpected_exceptions_keep_their_categories_for_fallback_profiles(): void
    {
        foreach (['http_only', 'adaptive', 'browser_likely'] as $profile) {
            $network = $this->runSample(new NetworkConnectionException(
                new Request('GET', 'https://example.test/item/1'),
                'synthetic network failure',
            ), $profile);
            $unexpected = $this->runSample(new RuntimeException('synthetic parser failure'), $profile);

            self::assertSame('network', $network['status']);
            self::assertSame('parse-error', $unexpected['status']);
        }
    }

    /** @param CrawlOutcomeDto|Throwable $outcome
     * @return array{status: string, item_count: int, error_code: ?string}
     */
    private function runSample(CrawlOutcomeDto|Throwable $outcome, string $profile): array
    {
        $sample = (new LiveCheckSampleSelector())->select([
            'slug' => 'profile-site',
            'name' => 'Profile Site',
            'adapter' => ['capabilities' => ['detail']],
            'runtime' => [
                'fetch' => ['profile' => $profile],
                'fixtureSamples' => [[
                    'url' => 'https://example.test/item/1',
                    'name' => 'detail',
                    'type' => 'detail',
                ]],
            ],
        ])[0];
        $runner = new LiveCheckReportRunner(
            crawl: static function () use ($outcome): CrawlOutcomeDto {
                if ($outcome instanceof Throwable) {
                    throw $outcome;
                }

                return $outcome;
            },
            sleepMicroseconds: static function (int $microseconds): void {
            },
            clockNanoseconds: static fn(): int => 0,
        );

        return $runner->run([$sample])[0];
    }
}
