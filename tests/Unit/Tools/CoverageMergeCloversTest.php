<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CoverageMergeCloversTest extends TestCase
{
    public function test_merges_statement_counts_with_maximum_hits(): void
    {
        $root = dirname(__DIR__, 3);
        $script = $root . '/tools/coverage-merge-clovers.php';
        $dir = sys_get_temp_dir() . '/crawlerx-clover-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($dir));

        $left = $dir . '/left.xml';
        $right = $dir . '/right.xml';
        $output = $dir . '/merged.xml';

        file_put_contents($left, $this->clover('Example.php', ['10' => 0, '11' => 2]));
        file_put_contents($right, $this->clover('Example.php', ['10' => 3, '11' => 0]));

        $command = [PHP_BINARY, $script, $output, $left, $right];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );
        self::assertIsResource($process);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        self::assertSame(0, $exit, is_string($stderr) ? $stderr : '');
        self::assertFileExists($output);

        $merged = simplexml_load_file($output);
        self::assertNotFalse($merged);
        $counts = [];
        foreach ($merged->xpath('//line[@type="stmt"]') ?: [] as $line) {
            $counts[(string) $line['num']] = (int) $line['count'];
        }
        self::assertSame(['10' => 3, '11' => 2], $counts);

        foreach ([$left, $right, $output] as $path) {
            unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @param array<string, int> $lines
     */
    private function clover(string $fileName, array $lines): string
    {
        $lineXml = '';
        foreach ($lines as $num => $count) {
            $lineXml .= sprintf('<line num="%s" type="stmt" count="%d"/>', $num, $count);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1">
    <metrics files="1" classes="0" statements="2" coveredstatements="1" elements="2" coveredelements="1"/>
    <file name="{$fileName}">
      {$lineXml}
    </file>
  </project>
</coverage>
XML;
    }
}
