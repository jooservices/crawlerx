<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

final class CoverageUnionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/coverage-union-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function test_a_statement_covered_by_either_suite_counts_once(): void
    {
        // A.php lines 1-4: unit covers 1,2; feature covers 2,3; line 4 is covered by nobody.
        $unit = $this->clover('A.php', [1 => 1, 2 => 1, 3 => 0, 4 => 0]);
        $feature = $this->clover('A.php', [1 => 0, 2 => 2, 3 => 1, 4 => 0]);

        [$exit, $out] = $this->runScript($unit, $feature, '75');

        self::assertSame(0, $exit);
        self::assertStringContainsString('union: 75.00% (3/4)', $out);
    }

    public function test_it_fails_when_the_union_is_below_the_minimum(): void
    {
        $unit = $this->clover('A.php', [1 => 1, 2 => 0]);
        $feature = $this->clover('A.php', [1 => 0, 2 => 0]);

        [$exit, $out] = $this->runScript($unit, $feature, '90');

        self::assertSame(1, $exit);
        self::assertStringContainsString('union: 50.00% (1/2)', $out);
        self::assertStringContainsString('below 90.00%', $out);
    }

    public function test_statements_that_exist_in_only_one_report_are_still_counted(): void
    {
        $unit = $this->clover('A.php', [1 => 1]);
        $feature = $this->clover('B.php', [1 => 1, 2 => 0]);

        [, $out] = $this->runScript($unit, $feature, '0');

        self::assertStringContainsString('union: 66.67% (2/3)', $out);
    }

    public function test_reports_without_any_statement_fail_instead_of_passing_as_one_hundred_percent(): void
    {
        $empty = $this->dir . '/empty.xml';
        file_put_contents($empty, '<?xml version="1.0"?><coverage><project/></coverage>');

        [$exit, $out] = $this->runScript($empty, $empty, '90');

        self::assertSame(1, $exit);
        self::assertStringContainsString('has no executable statements', $out);
    }

    public function test_a_single_empty_report_fails_even_when_the_other_suite_is_fully_covered(): void
    {
        $empty = $this->dir . '/empty.xml';
        file_put_contents($empty, '<?xml version="1.0"?><coverage><project/></coverage>');
        $full = $this->clover('A.php', [1 => 1, 2 => 1]);

        [$exitUnit, $outUnit] = $this->runScript($empty, $full, '90');
        [$exitFeature] = $this->runScript($full, $empty, '90');

        self::assertSame(1, $exitUnit);
        self::assertStringContainsString('The Unit clover report has no executable statements', $outUnit);
        self::assertSame(1, $exitFeature);
    }

    public function test_a_missing_report_is_an_error(): void
    {
        [$exit, $out] = $this->runScript($this->dir . '/missing.xml', $this->dir . '/missing2.xml', '90');

        self::assertSame(1, $exit);
        self::assertStringContainsString('Missing clover file', $out);
    }

    public function test_an_invalid_report_is_an_error(): void
    {
        $bad = $this->dir . '/bad.xml';
        file_put_contents($bad, 'not xml');

        [$exit, $out] = $this->runScript($bad, $bad, '90');

        self::assertSame(1, $exit);
        self::assertStringContainsString('invalid clover', $out);
    }

    /**
     * @param array<int, int> $lines line number => hit count
     */
    private function clover(string $file, array $lines): string
    {
        $xml = '<?xml version="1.0"?><coverage><project><file name="' . $file . '">';
        foreach ($lines as $number => $count) {
            $xml .= '<line num="' . $number . '" type="stmt" count="' . $count . '"/>';
        }
        $xml .= '</file></project></coverage>';

        $path = $this->dir . '/' . uniqid('c', true) . '.xml';
        file_put_contents($path, $xml);

        return $path;
    }

    /**
     * @return array{int, string}
     */
    private function runScript(string $unit, string $feature, string $minimum): array
    {
        $script = dirname(__DIR__, 3) . '/tools/coverage-union.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' '
            . escapeshellarg($unit) . ' ' . escapeshellarg($feature) . ' ' . escapeshellarg($minimum) . ' 2>&1';
        exec($command, $output, $exit);

        return [$exit, implode("\n", $output)];
    }
}
