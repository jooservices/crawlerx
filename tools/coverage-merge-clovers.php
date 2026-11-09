<?php

declare(strict_types=1);

/**
 * Merge one or more Clover XML reports into a single report.
 *
 * For duplicate files, keeps the maximum hit count per statement line so
 * parallel PHPUnit shards can be recombined before the coverage floor check.
 *
 * Usage: php tools/coverage-merge-clovers.php <output.xml> <input.xml>...
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/coverage-merge-clovers.php <output.xml> <input.xml>...\n");
    exit(1);
}

$outputPath = $argv[1];
$inputPaths = array_slice($argv, 2);

/** @var array<string, SimpleXMLElement> $fileNodes */
$fileNodes = [];
/** @var array<string, array<string, int>> $lineCounts */
$lineCounts = [];

foreach ($inputPaths as $inputPath) {
    if (! is_file($inputPath)) {
        fwrite(STDERR, "Missing clover input: {$inputPath}\n");
        exit(1);
    }

    $xml = simplexml_load_file($inputPath);
    if ($xml === false) {
        fwrite(STDERR, "Invalid clover input: {$inputPath}\n");
        exit(1);
    }

    foreach ($xml->xpath('//file') ?: [] as $file) {
        $name = (string) $file['name'];
        if ($name === '') {
            continue;
        }

        if (! isset($fileNodes[$name])) {
            $fileNodes[$name] = $file;
            $lineCounts[$name] = [];
        }

        foreach ($file->line as $line) {
            if ((string) $line['type'] !== 'stmt') {
                continue;
            }
            $num = (string) $line['num'];
            $lineCounts[$name][$num] = max($lineCounts[$name][$num] ?? 0, (int) $line['count']);
        }
    }
}

if ($fileNodes === []) {
    fwrite(STDERR, "No clover files found in inputs\n");
    exit(1);
}

ksort($fileNodes, SORT_STRING);

$document = new DOMDocument('1.0', 'UTF-8');
$document->formatOutput = true;
$coverage = $document->createElement('coverage');
$coverage->setAttribute('generated', (string) time());
$document->appendChild($coverage);

$project = $document->createElement('project');
$project->setAttribute('timestamp', (string) time());
$coverage->appendChild($project);

$statements = 0;
$covered = 0;
$classes = 0;

foreach ($fileNodes as $name => $file) {
    $imported = $document->importNode(dom_import_simplexml($file), true);
    if (! $imported instanceof DOMElement) {
        fwrite(STDERR, "Unable to import clover file node for {$name}\n");
        exit(1);
    }

    foreach ($imported->getElementsByTagName('line') as $line) {
        if (! $line instanceof DOMElement || $line->getAttribute('type') !== 'stmt') {
            continue;
        }
        $num = $line->getAttribute('num');
        $count = $lineCounts[$name][$num] ?? 0;
        $line->setAttribute('count', (string) $count);
        ++$statements;
        if ($count > 0) {
            ++$covered;
        }
    }

    $classes += $imported->getElementsByTagName('class')->length;
    $project->appendChild($imported);
}

$metrics = $document->createElement('metrics');
$metrics->setAttribute('files', (string) count($fileNodes));
$metrics->setAttribute('classes', (string) $classes);
$metrics->setAttribute('statements', (string) $statements);
$metrics->setAttribute('coveredstatements', (string) $covered);
$metrics->setAttribute('elements', (string) $statements);
$metrics->setAttribute('coveredelements', (string) $covered);
$project->insertBefore($metrics, $project->firstChild);

if ($document->save($outputPath) === false) {
    fwrite(STDERR, "Unable to write merged clover report\n");
    exit(1);
}

echo "Wrote {$outputPath} (" . count($fileNodes) . " files, {$covered}/{$statements} statements)\n";
