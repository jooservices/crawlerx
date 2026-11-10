<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Tools\FixturesMetaChecker;

require_once __DIR__ . '/fixtures/FixturesMetaChecker.php';

$root = dirname(__DIR__);
$report = (new FixturesMetaChecker())->check(
    $root . '/tests/Fixtures',
    __DIR__ . '/fixtures/meta-baseline.txt',
);

if ($report['missing_directory']) {
    fwrite(STDERR, "Fixtures directory is missing: tests/Fixtures\n");
}

foreach ($report['issues'] as $issue) {
    fwrite(STDOUT, sprintf("%s %s %s\n", $issue['code'], $issue['path'], $issue['detail']));
}

if ($report['baseline_file_count'] > 0) {
    $counts = [];
    foreach ($report['baseline_counts'] as $code => $count) {
        $counts[] = $code . '=' . $count;
    }
    fwrite(STDOUT, sprintf(
        "WARN BASELINED %d fixture(s): %s\n",
        $report['baseline_file_count'],
        implode(', ', $counts),
    ));
}

exit($report['exit_code']);
