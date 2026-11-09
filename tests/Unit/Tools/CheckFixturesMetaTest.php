<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use FilesystemIterator;
use JOOservices\CrawlerX\Tools\FixturesMetaChecker;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/tools/fixtures/FixturesMetaChecker.php';

final class CheckFixturesMetaTest extends TestCase
{
    private string $temporaryDirectory;

    private string $fixturesDirectory;

    private string $baselineFile;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/fixtures-meta-' . bin2hex(random_bytes(6));
        $this->fixturesDirectory = $this->temporaryDirectory . '/Fixtures';
        $this->baselineFile = $this->temporaryDirectory . '/meta-baseline.txt';
        mkdir($this->fixturesDirectory, 0777, true);
        file_put_contents($this->baselineFile, "# Test baseline\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function test_valid_fixture_metadata_passes_with_matching_sha256(): void
    {
        $this->writeFixture('source/detail.html', '<html>fixture</html>');

        $report = $this->check();

        self::assertSame(0, $report['exit_code']);
        self::assertSame([], $report['issues']);
    }

    public function test_missing_metadata_is_reported(): void
    {
        $this->writeFixture('source/detail.html', '<html>fixture</html>', metadata: false);

        $report = $this->check();

        self::assertSame(1, $report['exit_code']);
        self::assertSame('MISSING_META', $report['issues'][0]['code']);
    }

    public function test_invalid_json_is_reported(): void
    {
        $fixture = $this->writeFixture('source/detail.html', '<html>fixture</html>', metadata: false);
        file_put_contents($fixture . '.meta.json', '{invalid');

        $report = $this->check();

        self::assertSame(['BAD_JSON'], array_column($report['issues'], 'code'));
    }

    public function test_missing_required_keys_are_reported(): void
    {
        $fixture = $this->writeFixture('source/detail.html', '<html>fixture</html>', metadata: false);
        file_put_contents($fixture . '.meta.json', json_encode(['source_url' => 'https://example.test/source'], JSON_THROW_ON_ERROR));

        $report = $this->check();

        self::assertSame(['MISSING_KEY'], array_column($report['issues'], 'code'));
    }

    public function test_invalid_metadata_values_are_reported(): void
    {
        $contents = '<html>fixture</html>';
        $metadata = $this->validMetadata($contents);
        $metadata['http_status'] = 600;
        $this->writeFixture('source/detail.html', $contents, $metadata);

        $report = $this->check();

        self::assertSame(['BAD_VALUE'], array_column($report['issues'], 'code'));
        self::assertStringContainsString('http_status', $report['issues'][0]['detail']);
    }

    public function test_basic_iso8601_utc_offset_is_valid(): void
    {
        $report = $this->checkCapturedAt('2026-11-10T12:00:00+0700');

        self::assertSame([], $report['issues']);
    }

    public function test_iso8601_utc_offset_at_its_maximum_is_valid(): void
    {
        $report = $this->checkCapturedAt('2026-11-10T12:00:00-14:00');

        self::assertSame([], $report['issues']);
    }

    public function test_iso8601_utc_offset_over_its_maximum_is_invalid(): void
    {
        foreach (['2026-11-10T12:00:00+14:01', '2026-11-10T12:00:00+24:00'] as $capturedAt) {
            $report = $this->checkCapturedAt($capturedAt);

            self::assertSame(['BAD_VALUE'], array_column($report['issues'], 'code'));
            self::assertStringContainsString('captured_at', $report['issues'][0]['detail']);
        }
    }

    public function test_hash_mismatch_is_reported(): void
    {
        $contents = '<html>fixture</html>';
        $metadata = $this->validMetadata($contents);
        $metadata['content_hash'] = hash('sha256', 'different fixture bytes');
        $this->writeFixture('source/detail.html', $contents, $metadata);

        $report = $this->check();

        self::assertSame(['HASH_MISMATCH'], array_column($report['issues'], 'code'));
    }

    public function test_baselined_legacy_fixture_is_reported_as_a_warning_and_passes(): void
    {
        $this->writeFixture('legacy/detail.html', '<html>legacy</html>', metadata: false);
        file_put_contents($this->baselineFile, "# Test baseline\nlegacy/detail.html\n");

        $report = $this->check();

        self::assertSame(0, $report['exit_code']);
        self::assertSame([], $report['issues']);
        self::assertSame(1, $report['baseline_file_count']);
        self::assertSame(['MISSING_META' => 1], $report['baseline_counts']);
    }

    public function test_missing_fixtures_directory_returns_exit_code_two(): void
    {
        $report = (new FixturesMetaChecker())->check(
            $this->temporaryDirectory . '/missing',
            $this->baselineFile,
        );

        self::assertSame(2, $report['exit_code']);
        self::assertTrue($report['missing_directory']);
    }

    public function test_issues_are_sorted_by_code_then_path(): void
    {
        $this->writeFixture('z/detail.html', '<html>fixture</html>', metadata: false);
        $invalidJson = $this->writeFixture('a/detail.html', '<html>fixture</html>', metadata: false);
        file_put_contents($invalidJson . '.meta.json', '{invalid');

        $report = $this->check();

        self::assertSame(['BAD_JSON', 'MISSING_META'], array_column($report['issues'], 'code'));
        self::assertSame(['a/detail.html', 'z/detail.html'], array_column($report['issues'], 'path'));
    }

    /** @return array<string, mixed> */
    private function validMetadata(string $contents): array
    {
        return [
            'source_url' => 'https://example.test/source',
            'final_url' => 'https://example.test/final',
            'captured_at' => '2026-11-10T12:00:00.000Z',
            'http_status' => 200,
            'content_hash' => hash('sha256', $contents),
            'target_type' => 'detail',
            'fetch_method' => 'http',
            'sanitized' => false,
        ];
    }

    /** @return array{exit_code: int, issues: list<array{code: string, path: string, detail: string}>, baseline_file_count: int, baseline_counts: array<string, int>, missing_directory: bool} */
    private function checkCapturedAt(string $capturedAt): array
    {
        $contents = '<html>fixture</html>';
        $metadata = $this->validMetadata($contents);
        $metadata['captured_at'] = $capturedAt;
        $this->writeFixture('source/detail.html', $contents, $metadata);

        return $this->check();
    }

    /**
     * @param array<string, mixed>|false|null $metadata
     */
    private function writeFixture(string $relativePath, string $contents, array|false|null $metadata = null): string
    {
        $path = $this->fixturesDirectory . '/' . $relativePath;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $contents);

        if ($metadata !== false) {
            $metadata ??= $this->validMetadata($contents);
            file_put_contents($path . '.meta.json', json_encode($metadata, JSON_THROW_ON_ERROR));
        }

        return $path;
    }

    /** @return array{exit_code: int, issues: list<array{code: string, path: string, detail: string}>, baseline_file_count: int, baseline_counts: array<string, int>, missing_directory: bool} */
    private function check(): array
    {
        return (new FixturesMetaChecker())->check($this->fixturesDirectory, $this->baselineFile);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new FilesystemIterator($directory) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                $this->removeDirectory($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}
