<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Tools\ReadmeSitesChecker;

require_once __DIR__ . '/ReadmeSitesChecker.php';

$repositoryRoot = dirname(__DIR__);
$result = (new ReadmeSitesChecker())->check(
    $repositoryRoot . '/README.md',
    $repositoryRoot . '/src/Adapters',
);

foreach ($result->messages as $message) {
    fwrite(STDOUT, $message . PHP_EOL);
}

exit($result->exitCode);
