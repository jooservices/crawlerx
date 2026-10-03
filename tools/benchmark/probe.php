<?php

declare(strict_types=1);

use JOOservices\CrawlerX\Dto\HttpProfileDto;
use JOOservices\CrawlerX\Dto\PlaywrightProfileDto;
use JOOservices\CrawlerX\Dto\SiteProfileDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;
use JOOservices\CrawlerX\Fetch\BrowserServiceProcessRunner;
use JOOservices\CrawlerX\Fetch\FetchRuntimeConfig;
use JOOservices\CrawlerX\Fetch\Handlers\FlaresolverrFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\HttpFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PlaywrightFamilyFetchHandler;
use JOOservices\CrawlerX\Fetch\Handlers\PuppeteerStealthFetchHandler;
use JOOservices\CrawlerX\Fetch\ProcOpenProcessRunner;
use JOOservices\CrawlerX\Services\ClientFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$values = [];
foreach (array_slice($argv, 1) as $argument) {
    if (! str_starts_with($argument, '--') || ! str_contains($argument, '=')) {
        continue;
    }
    [$name, $value] = explode('=', substr($argument, 2), 2);
    $values[$name] = $value;
}

$url = $values['url'] ?? '';
$method = FetchMethod::tryFrom($values['method'] ?? '');
if ($url === '' || $method === null) {
    fwrite(STDERR, "Usage: php tools/benchmark/probe.php --url=URL --method=METHOD\n");
    exit(2);
}

$runtime = FetchRuntimeConfig::fromEnvironment();
$processRunner = new ProcOpenProcessRunner();
$browserRunner = $runtime->browserServiceUrl === null
    ? $processRunner
    : new BrowserServiceProcessRunner($runtime->browserServiceUrl);
$profile = new SiteProfileDto(
    slug: 'benchmark',
    displayName: 'CrawlerX benchmark',
    baseUrl: $url,
    fetchProfile: FetchProfile::BrowserLikely,
    fetchChain: FetchMethod::browserChain(),
    http: new HttpProfileDto(),
    playwright: new PlaywrightProfileDto(),
);
$handler = match ($method) {
    FetchMethod::Http => new HttpFetchHandler(new ClientFactory()),
    FetchMethod::Playwright, FetchMethod::PlaywrightStealth, FetchMethod::ChromeStealth => new PlaywrightFamilyFetchHandler($runtime, $browserRunner),
    FetchMethod::PuppeteerStealth => new PuppeteerStealthFetchHandler($runtime, $browserRunner),
    FetchMethod::Flaresolverr => new FlaresolverrFetchHandler($runtime),
    default => throw new InvalidArgumentException("Unsupported benchmark method: {$method->value}"),
};

function usageSnapshot(): array
{
    return function_exists('getrusage') ? \getrusage() : [];
}

function cpuSeconds(array $before, array $after): float
{
    $user = ((int) ($after['ru_utime.tv_sec'] ?? 0) * 1_000_000 + (int) ($after['ru_utime.tv_usec'] ?? 0))
        - ((int) ($before['ru_utime.tv_sec'] ?? 0) * 1_000_000 + (int) ($before['ru_utime.tv_usec'] ?? 0));
    $system = ((int) ($after['ru_stime.tv_sec'] ?? 0) * 1_000_000 + (int) ($after['ru_stime.tv_usec'] ?? 0))
        - ((int) ($before['ru_stime.tv_sec'] ?? 0) * 1_000_000 + (int) ($before['ru_stime.tv_usec'] ?? 0));

    return max(0.0, ($user + $system) / 1_000_000);
}

$before = usageSnapshot();
$started = hrtime(true);
try {
    $result = $handler->fetch($url, $profile, $method);
    $payload = [
        'ok' => $result->ok,
        'status' => $result->status,
        'bytes' => strlen($result->body),
        'challenge' => $result->challengeDetected,
        'error' => $result->error,
    ];
} catch (Throwable $exception) {
    $payload = [
        'ok' => false,
        'status' => 0,
        'bytes' => 0,
        'challenge' => false,
        'error' => $exception->getMessage(),
    ];
}
$after = usageSnapshot();
$payload['method'] = $method->value;
$payload['url'] = $url;
$payload['wall_ms'] = (int) round((hrtime(true) - $started) / 1_000_000);
$payload['php_cpu_seconds'] = cpuSeconds($before, $after);
$payload['php_peak_rss_bytes'] = memory_get_peak_usage(true);

fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
exit($payload['ok'] === true ? 0 : 1);
