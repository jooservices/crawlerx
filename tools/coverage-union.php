<?php

declare(strict_types=1);

/**
 * Enforce a minimum line coverage on the UNION of the Unit and Feature suites.
 *
 * A statement counts as covered when at least one suite executed it. This is
 * what "repository coverage" means; tools/coverage-merge.php only concatenates
 * the reports for Codecov/Sonar and double counts shared statements.
 *
 * Usage: php tools/coverage-union.php [unit.xml] [feature.xml] [minimum-percent]
 */

$unitPath = $argv[1] ?? 'build/coverage/clover-Unit.xml';
$featurePath = $argv[2] ?? 'build/coverage/clover-Feature.xml';
$threshold = isset($argv[3]) ? (float) $argv[3] : 90.0;

/**
 * @return array<string, bool> statement id (file:line) => covered
 */
function statements(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing clover file: {$path}\n");
        exit(1);
    }

    $xml = simplexml_load_file($path);
    if ($xml === false) {
        fwrite(STDERR, basename($path) . ": invalid clover\n");
        exit(1);
    }

    $statements = [];
    foreach ($xml->xpath('//file') ?: [] as $file) {
        $name = (string) $file['name'];
        foreach ($file->line as $line) {
            if ((string) $line['type'] !== 'stmt') {
                continue;
            }

            $statements[$name . ':' . (int) $line['num']] = (int) $line['count'] > 0;
        }
    }

    return $statements;
}

$unit = statements($unitPath);
$feature = statements($featurePath);

foreach (['Unit' => $unit, 'Feature' => $feature] as $suite => $suiteStatements) {
    if ($suiteStatements === []) {
        // A suite that reports nothing did not run; its absence must not be hidden by the other suite.
        fwrite(STDERR, "The {$suite} clover report has no executable statements; refusing to pass.\n");
        exit(1);
    }
}

$all = $unit + $feature;
$covered = 0;
foreach (array_keys($all) as $id) {
    if (($unit[$id] ?? false) || ($feature[$id] ?? false)) {
        ++$covered;
    }
}

$total = count($all);
if ($total === 0) {
    // An empty report means coverage was not measured; never treat it as 100%.
    fwrite(STDERR, "No executable statements found in the clover reports; refusing to pass an empty report.\n");
    exit(1);
}

$percent = ($covered / $total) * 100.0;

echo 'union: ' . number_format($percent, 2) . "% ({$covered}/{$total}), minimum " . number_format($threshold, 2) . "%\n";

if ($percent < $threshold) {
    fwrite(STDERR, 'Union coverage ' . number_format($percent, 2) . '% is below ' . number_format($threshold, 2) . "%\n");
    exit(1);
}
