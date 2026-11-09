<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Dto\CrawlErrorDto;
use JOOservices\CrawlerX\Dto\CrawlOutcomeDto;
use JOOservices\CrawlerX\Enums\CrawlErrorCode;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';

final class LiveCheckReportRunnerTest extends TestCase
{
    public function test_it_limits_each_adapter_to_two_requests_and_spaces_requests_by_host(): void
    {
        $now = 0;
        $requestedAt = [];
        $crawl = static function (array $sample) use (&$now, &$requestedAt): CrawlOutcomeDto {
            $requestedAt[] = [$sample['slug'], $now];
            $now += 100_000_000;

            return CrawlOutcomeDto::failure(new CrawlErrorDto(CrawlErrorCode::Network, 'synthetic response'));
        };
        $sleep = static function (int $microseconds) use (&$now): void {
            $now += $microseconds * 1_000;
        };
        $clock = static fn(): int => $now;
        $runner = new LiveCheckReportRunner($crawl, $sleep, $clock);
        $sample = static fn(string $slug, string $url, string $name): array => [
            'slug' => $slug,
            'adapter_name' => $slug,
            'role' => 'listing',
            'name' => $name,
            'type' => CrawlType::Listing,
            'url' => $url,
        ];

        $results = $runner->run([
            $sample('site-a', 'https://shared.test/list', 'one'),
            $sample('site-a', 'https://shared.test/detail', 'two'),
            $sample('site-a', 'https://shared.test/third', 'three'),
            $sample('site-b', 'https://shared.test/list', 'four'),
        ]);

        self::assertCount(3, $results);
        self::assertSame(2, count(array_filter($requestedAt, static fn(array $request): bool => $request[0] === 'site-a')));
        self::assertGreaterThanOrEqual(10_000_000_000, $requestedAt[1][1] - $requestedAt[0][1]);
        self::assertGreaterThanOrEqual(10_000_000_000, $requestedAt[2][1] - $requestedAt[1][1]);
        self::assertSame('network', $results[0]['status']);
    }
}
