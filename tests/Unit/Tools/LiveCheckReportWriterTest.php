<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use DateTimeImmutable;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportWriter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../tools/live-check/LiveCheckReportWriter.php';

final class LiveCheckReportWriterTest extends TestCase
{
    public function test_it_writes_markdown_and_json_reports_from_result_records(): void
    {
        $directory = sys_get_temp_dir() . '/crawlerx-live-check-' . bin2hex(random_bytes(5));
        $writer = new LiveCheckReportWriter();
        $generatedAt = new DateTimeImmutable('2026-11-09T12:00:00+07:00');
        $results = [[
            'slug' => 'sample-site',
            'adapter_name' => 'Sample | Site',
            'sample_role' => 'listing',
            'sample_name' => 'listing.html',
            'sample_type' => 'listing',
            'url' => 'https://example.test/list',
            'status' => 'ok',
            'item_count' => 2,
            'duration_ms' => 1_250,
            'error_code' => null,
        ]];

        try {
            $report = $writer->write($results, $directory, $generatedAt);
            self::assertFileExists($report['json_path']);
            self::assertFileExists($report['markdown_path']);
            self::assertStringContainsString('"adapter_count": 1', $report['json']);
            self::assertStringContainsString('2026-11-09T05:00:00+00:00', $report['json']);
            self::assertStringContainsString('Sample \\| Site', $report['markdown']);
            self::assertStringContainsString('| ok | 2 | 1.25s |', $report['markdown']);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
