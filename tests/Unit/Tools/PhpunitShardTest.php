<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PhpunitShardTest extends TestCase
{
    public function test_shards_partition_all_unit_test_files_without_overlap(): void
    {
        $root = dirname(__DIR__, 3);
        $suite = $root . '/tests/Unit';
        $script = $root . '/tools/ci/phpunit-shard.php';
        $total = 2;
        $lists = [];

        for ($shard = 1; $shard <= $total; ++$shard) {
            $output = sys_get_temp_dir() . '/crawlerx-shard-unit-' . $shard . '.paths';
            $command = [
                PHP_BINARY,
                $script,
                $suite,
                (string) $shard,
                (string) $total,
                $output,
            ];
            $process = proc_open(
                $command,
                [1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root,
            );
            self::assertIsResource($process);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            self::assertSame(0, $exit, is_string($stderr) ? $stderr : '');
            self::assertFileExists($output);
            $paths = array_values(array_filter(explode("\n", (string) file_get_contents($output)), static fn(string $line): bool => $line !== ''));
            self::assertNotSame([], $paths);
            $lists[$shard] = $paths;
            unlink($output);
        }

        $combined = [...$lists[1], ...$lists[2]];
        self::assertSame(array_values(array_unique($combined)), $combined);
        self::assertSame(count($lists[1]) + count($lists[2]), count($combined));
    }
}
