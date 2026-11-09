<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use JOOservices\CrawlerX\Tools\ReadmeSitesChecker;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/tools/ReadmeSitesChecker.php';

final class CheckReadmeSitesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/readme-sites-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($this->directory));
        self::assertTrue(mkdir($this->adapterDirectory(), 0777, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->adapterDirectory() . '/*/manifest.json') ?: [] as $manifestPath) {
            unlink($manifestPath);
            rmdir(dirname($manifestPath));
        }

        rmdir($this->adapterDirectory());
        rmdir(dirname($this->adapterDirectory()));
        unlink($this->readmePath());
        rmdir($this->directory);
    }

    public function test_all_sites_match_by_name_or_slug(): void
    {
        $this->writeManifest('alpha', 'Alpha');
        $this->writeManifest('beta', 'Beta');
        $this->writeReadme(['Alpha (regional site)', '`beta`']);

        $result = $this->check();

        self::assertSame(0, $result->exitCode);
        self::assertSame([], $result->messages);
    }

    public function test_it_reports_a_manifest_missing_from_the_readme(): void
    {
        $this->writeManifest('alpha', 'Alpha');
        $this->writeReadme([]);

        $result = $this->check();

        self::assertSame(1, $result->exitCode);
        self::assertSame(['MISSING_IN_README alpha'], $result->messages);
    }

    public function test_it_reports_a_readme_site_without_a_manifest(): void
    {
        $this->writeManifest('alpha', 'Alpha');
        $this->writeReadme(['Alpha', 'Unknown Site']);

        $result = $this->check();

        self::assertSame(1, $result->exitCode);
        self::assertSame(['UNKNOWN_IN_README Unknown Site'], $result->messages);
    }

    public function test_it_sorts_mismatch_messages(): void
    {
        $this->writeManifest('zulu', 'Zulu');
        $this->writeManifest('alpha', 'Alpha');
        $this->writeReadme(['Zulu Unknown', 'Alpha Unknown']);

        $result = $this->check();

        self::assertSame(1, $result->exitCode);
        self::assertSame([
            'MISSING_IN_README alpha',
            'MISSING_IN_README zulu',
            'UNKNOWN_IN_README Alpha Unknown',
            'UNKNOWN_IN_README Zulu Unknown',
        ], $result->messages);
    }

    public function test_missing_supported_sites_table_returns_a_clear_error_and_exit_code_two(): void
    {
        $this->writeManifest('alpha', 'Alpha');
        file_put_contents($this->readmePath(), '# Readme' . PHP_EOL);

        $result = $this->check();

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('README supported-sites table not found', $result->messages[0]);
    }

    /** @param list<string> $rows */
    private function writeReadme(array $rows): void
    {
        $tableRows = ['| --- | --- | --- |'];
        foreach ($rows as $name) {
            $tableRows[] = sprintf('| %s | — | — |', $name);
        }

        file_put_contents($this->readmePath(), "## Supported sites\n\n| Site | Slug | Listing |\n" . implode("\n", $tableRows) . "\n");
    }

    private function writeManifest(string $slug, string $name): void
    {
        $path = $this->adapterDirectory() . '/' . $slug;
        self::assertTrue(mkdir($path));
        file_put_contents($path . '/manifest.json', json_encode(['slug' => $slug, 'name' => $name], JSON_THROW_ON_ERROR));
    }

    private function check(): \JOOservices\CrawlerX\Tools\ReadmeSitesCheckResult
    {
        return (new ReadmeSitesChecker())->check($this->readmePath(), $this->adapterDirectory());
    }

    private function readmePath(): string
    {
        return $this->directory . '/README.md';
    }

    private function adapterDirectory(): string
    {
        return $this->directory . '/src/Adapters';
    }
}
