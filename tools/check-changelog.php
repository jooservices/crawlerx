<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Tools\CheckChangelog;

require_once __DIR__ . '/CheckChangelog.php';

$path = $argv[1] ?? dirname(__DIR__) . '/CHANGELOG.md';
if (!is_file($path)) {
    fwrite(STDERR, "Changelog file is missing.\n");
    exit(2);
}

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Changelog file could not be read.\n");
    exit(2);
}

$diagnostics = CheckChangelog::validate($contents);
foreach ($diagnostics as $diagnostic) {
    fwrite(STDOUT, sprintf("%s line %d %s\n", $diagnostic['code'], $diagnostic['line'], $diagnostic['detail']));
}

exit($diagnostics === [] ? 0 : 1);
