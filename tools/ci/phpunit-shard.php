<?php

declare(strict_types=1);

/**
 * Select a stable shard of PHPUnit test files for a suite directory.
 *
 * Usage: php tools/ci/phpunit-shard.php <suite-dir> <shard> <total> <output-list>
 * Writes one absolute path per line to the output list file.
 */

if ($argc !== 5) {
    fwrite(STDERR, "Usage: php tools/ci/phpunit-shard.php <suite-dir> <shard> <total> <output-list>\n");
    exit(1);
}

$suiteDir = $argv[1];
$shard = filter_var($argv[2], FILTER_VALIDATE_INT);
$total = filter_var($argv[3], FILTER_VALIDATE_INT);
$outputList = $argv[4];

if (! is_dir($suiteDir) || $shard === false || $total === false || $shard < 1 || $total < 1 || $shard > $total) {
    fwrite(STDERR, "Invalid suite directory or shard arguments\n");
    exit(1);
}

$outputDir = dirname($outputList);
if ($outputDir !== '' && $outputDir !== '.' && ! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($suiteDir, FilesystemIterator::SKIP_DOTS),
);

$files = [];
foreach ($iterator as $fileInfo) {
    if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
        continue;
    }

    $name = $fileInfo->getFilename();
    if (! str_ends_with($name, 'Test.php')) {
        continue;
    }

    $files[] = $fileInfo->getPathname();
}

sort($files, SORT_STRING);

$selected = [];
foreach ($files as $index => $path) {
    if (($index % $total) === ($shard - 1)) {
        $selected[] = $path;
    }
}

if ($selected === []) {
    fwrite(STDERR, "Shard {$shard}/{$total} selected no test files under {$suiteDir}\n");
    exit(1);
}

if (file_put_contents($outputList, implode("\n", $selected) . "\n") === false) {
    fwrite(STDERR, "Unable to write shard list: {$outputList}\n");
    exit(1);
}

fwrite(STDERR, sprintf("Shard %d/%d: %d of %d test files\n", $shard, $total, count($selected), count($files)));
