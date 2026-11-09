<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckClassifier.php';
require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportRunner.php';

final class LiveCheckReportRunnerFailureTest extends TestCase
{
    public function test_unexpected_crawl_exceptions_are_not_reported_as_network_failures(): void
    {
        $runner = new LiveCheckReportRunner(
            crawl: static function (): never {
                throw new RuntimeException('synthetic parser failure');
            },
            sleepMicroseconds: static function (int $microseconds): void {
            },
            clockNanoseconds: static fn(): int => 0,
        );
        $sample = [
            'slug' => 'sample-site',
            'adapter_name' => 'Sample Site',
            'role' => 'detail',
            'name' => 'detail.html',
            'type' => CrawlType::Detail,
            'url' => 'https://example.test/item/1',
        ];

        self::assertSame('parse-error', $runner->run([$sample])[0]['status']);
    }
}
