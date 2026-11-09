<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Tools\TestRunner\ChangedTestMapper;
use JOOservices\CrawlerX\Tools\TestRunner\ChangedTestPlan;

require_once __DIR__ . '/TestRunner/ChangedTestPlan.php';
require_once __DIR__ . '/TestRunner/ChangedTestMapper.php';

$root = dirname(__DIR__);
$base = 'origin/develop';

for ($index = 1; $index < $argc; ++$index) {
    $argument = $argv[$index];

    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, "Usage: php tools/test-changed.php [--base=<git-ref>]\n");
        exit(0);
    }

    if ($argument === '--base' && isset($argv[$index + 1])) {
        $base = $argv[++$index];
        continue;
    }

    if (str_starts_with($argument, '--base=')) {
        $base = substr($argument, strlen('--base='));
        continue;
    }

    fwrite(STDERR, "Usage: php tools/test-changed.php [--base=<git-ref>]\n");
    exit(2);
}

if ($base === '') {
    fwrite(STDERR, "The --base reference cannot be empty.\n");
    exit(2);
}

[$diffExit, $diffOutput, $diffError] = capture(['git', 'diff', '--name-only', '--diff-filter=ACMRTD', $base, '--'], $root);
[$untrackedExit, $untrackedOutput, $untrackedError] = capture(['git', 'ls-files', '--others', '--exclude-standard'], $root);

if ($diffExit !== 0 || $untrackedExit !== 0) {
    $error = trim($diffError . "\n" . $untrackedError);
    $plan = new ChangedTestPlan(true, [], [], 'Could not compare changes with ' . $base . ($error === '' ? '.' : ': ' . $error));
} else {
    $changedFiles = array_values(array_unique(array_filter([
        ...explode("\n", trim($diffOutput)),
        ...explode("\n", trim($untrackedOutput)),
    ], static fn(string $path): bool => $path !== '')));
    $plan = (new ChangedTestMapper($root))->map($changedFiles);
}

if ($plan->fullSuite) {
    fwrite(STDOUT, 'Running the full test suite: ' . ($plan->reason ?? 'full-suite fallback') . "\n");
    exit(run([PHP_BINARY, $root . '/vendor/bin/phpunit', '--no-coverage'], $root));
}

if ($plan->testPaths !== []) {
    fwrite(STDOUT, 'Running targeted test paths: ' . implode(', ', $plan->testPaths) . "\n");
    $exit = run([PHP_BINARY, $root . '/vendor/bin/phpunit', '--no-coverage', ...$plan->testPaths], $root);
    if ($exit !== 0) {
        exit($exit);
    }
}

$filter = $plan->fixtureFilter();
if ($filter !== null) {
    fwrite(STDOUT, 'Running manifest fixture tests for: ' . implode(', ', $plan->fixtureSlugs) . "\n");
    foreach ([
        'tests/Unit/ManifestFixtureExecutorTest.php',
        'tests/Feature/AllAdaptersCrawlTest.php',
    ] as $fixtureTest) {
        if (! is_file($root . '/' . $fixtureTest)) {
            continue;
        }

        $exit = run([
            PHP_BINARY,
            $root . '/vendor/bin/phpunit',
            '--no-coverage',
            '--filter',
            $filter,
            $fixtureTest,
        ], $root);
        if ($exit !== 0) {
            exit($exit);
        }
    }
}

exit(0);

/**
 * @param list<string> $command
 * @return array{int, string, string}
 */
function capture(array $command, string $workingDirectory): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory);
    if (! is_resource($process)) {
        return [1, '', 'Could not start ' . $command[0] . '.'];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        proc_close($process),
        is_string($stdout) ? $stdout : '',
        is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @param list<string> $command
 */
function run(array $command, string $workingDirectory): int
{
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $workingDirectory);
    if (! is_resource($process)) {
        fwrite(STDERR, 'Could not start ' . $command[0] . ".\n");

        return 1;
    }

    return proc_close($process);
}
