<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Tools\ManifestConsistency\ManifestChecker;

require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/ManifestChecker.php';

try {
    $problems = (new ManifestChecker(dirname(__DIR__) . '/src/Adapters'))->check();
} catch (RuntimeException $exception) {
    fwrite(STDERR, sprintf("ERROR %s\n", $exception->getMessage()));
    exit(2);
}

foreach ($problems as $problem) {
    fwrite(STDOUT, $problem . PHP_EOL);
}

exit($problems === [] ? 0 : 1);
