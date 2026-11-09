<?php

declare(strict_types=1);

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Tools\Canary\Env;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportRunner;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckRequestLimiter;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckReportWriter;
use JOOservices\CrawlerX\Tools\LiveCheck\LiveCheckSampleSelector;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/canary/Env.php';
require __DIR__ . '/LiveCheckSampleSelector.php';
require __DIR__ . '/LiveCheckClassifier.php';
require __DIR__ . '/LiveCheckRequestLimiter.php';
require __DIR__ . '/LiveCheckReportRunner.php';
require __DIR__ . '/LiveCheckReportWriter.php';

Env::applyToProcess(dirname(__DIR__, 2) . '/.env');

$usage = <<<'HELP'
Usage:
  composer live-check:report
  composer live-check:report -- --only=SLUG [--proxy=URL]

Checks one listing and one detail sample per adapter when available. A gallery
sample is used for the detail check when an adapter has no detail sample.
Requests to the same host are at least 10 seconds apart. Reports are written
to build/live-check/ as Markdown and JSON files.

Options:
  --only=SLUG       Check one adapter manifest
  --proxy=URL       Route requests through this proxy (credentials are not reported)
  --proxy URL       Equivalent proxy syntax
  --help, -h        Show this help
HELP;

$only = null;
$proxy = null;
$arguments = array_slice($argv, 1);
for ($index = 0; $index < count($arguments); ++$index) {
    $argument = $arguments[$index];
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, $usage . "\n");
        exit(0);
    }

    if (str_starts_with($argument, '--only=')) {
        $only = substr($argument, strlen('--only='));
        if ($only === '') {
            failUsage('--only requires a non-empty adapter slug.');
        }
        continue;
    }

    if (str_starts_with($argument, '--proxy=')) {
        $proxy = substr($argument, strlen('--proxy='));
        if ($proxy === '') {
            failUsage('--proxy requires a non-empty proxy URL.');
        }
        continue;
    }

    if ($argument === '--proxy') {
        $proxy = $arguments[$index + 1] ?? '';
        if ($proxy === '' || str_starts_with($proxy, '--')) {
            failUsage('--proxy requires a proxy URL.');
        }
        ++$index;
        continue;
    }

    failUsage("Unknown option [{$argument}].");
}

$manifestPaths = glob(dirname(__DIR__, 2) . '/src/Adapters/*/manifest.json') ?: [];
sort($manifestPaths);
$selector = new LiveCheckSampleSelector();
$samples = [];
$foundOnly = $only === null;

foreach ($manifestPaths as $manifestPath) {
    $rawManifest = file_get_contents($manifestPath);
    if (! is_string($rawManifest)) {
        fwrite(STDERR, "Unable to read an adapter manifest.\n");
        exit(2);
    }

    try {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($rawManifest, true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        fwrite(STDERR, 'Invalid manifest JSON: ' . $exception->getMessage() . "\n");
        exit(2);
    }

    if ($only !== null && ($manifest['slug'] ?? null) !== $only) {
        continue;
    }

    $foundOnly = true;
    try {
        array_push($samples, ...$selector->select($manifest));
    } catch (InvalidArgumentException $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(2);
    }
}

if (! $foundOnly) {
    fwrite(STDERR, "No adapter manifest found for --only={$only}.\n");
    exit(2);
}

$sleepMicroseconds = static function (int $microseconds): void {
    usleep($microseconds);
};
$clockNanoseconds = static fn(): int => hrtime(true);
$requestLimiter = new LiveCheckRequestLimiter($sleepMicroseconds, $clockNanoseconds);
CrawlerXFactory::configure(
    beforeOutboundRequest: static function (string $url) use ($requestLimiter): bool {
        return $requestLimiter->beforeRequest($url);
    },
);
$crawl = static function (array $sample) use ($proxy, $requestLimiter): \JOOservices\CrawlerX\Dto\CrawlOutcomeDto {
    return $requestLimiter->forAdapter(
        $sample['slug'],
        static function () use ($sample, $proxy): \JOOservices\CrawlerX\Dto\CrawlOutcomeDto {
            return CrawlerX::url($sample['url'])
                ->site($sample['slug'])
                ->type($sample['type'])
                ->options(new CrawlOptionsDto(http: new HttpOptionsDto(proxy: $proxy)))
                ->tryCrawl();
        },
    );
};
$runner = new LiveCheckReportRunner(
    crawl: $crawl,
    sleepMicroseconds: $sleepMicroseconds,
    clockNanoseconds: $clockNanoseconds,
);

$results = $runner->run($samples);
CrawlerXFactory::reset();
$generatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$report = (new LiveCheckReportWriter())->write(
    $results,
    dirname(__DIR__, 2) . '/build/live-check',
    $generatedAt,
);

printf("Checked %d adapter(s), %d sample(s).\n", count(array_unique(array_column($results, 'slug'))), count($results));
printf("Markdown: %s\nJSON: %s\n", $report['markdown_path'], $report['json_path']);
foreach (['ok', 'empty', 'blocked', 'parse-error', 'network'] as $status) {
    $count = count(array_filter($results, static fn(array $result): bool => $result['status'] === $status));
    printf("%-12s %d\n", $status, $count);
}

exit(count(array_filter($results, static fn(array $result): bool => $result['status'] !== 'ok')) === 0 ? 0 : 1);

function failUsage(string $message): never
{
    fwrite(STDERR, $message . "\nRun with --help for usage.\n");
    exit(2);
}
