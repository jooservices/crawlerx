<?php

declare(strict_types=1);

use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\CrawlerXFactory;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchChainDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    fwrite(STDOUT, <<<'HELP'
Usage:
  php tools/live-check.php URL [options]
  php tools/live-check.php [--site=SLUG]

URL mode maps directly to CrawlerX::url(URL) and prints the result DTO as JSON.
With no URL, one canonical live target per registered site is checked.

Builder options:
  --site=SLUG
  --type=listing|detail|performer_listing|performer_detail
  --page=NUMBER
  --try                         Call tryCrawl() instead of crawl()

Crawl/fetch options:
  --fetch-profile=http_only|browser_likely|adaptive
  --fetch-method=http|curl_impersonate|playwright|playwright_stealth|
                 chrome_stealth|puppeteer_stealth|flaresolverr
  --fetch-chain=METHOD,METHOD   Explicit ordered fetch chain
  --no-fallback
  --timeout=SECONDS
  --verify-ssl | --no-verify-ssl
  --header='Name: Value'        Repeat for multiple HTTP headers
HELP);
    exit(0);
}

$urlArgument = null;
foreach ($arguments as $argument) {
    if (! str_starts_with($argument, '-')) {
        if ($urlArgument !== null) {
            fwrite(STDERR, "Only one URL may be supplied.\n");
            exit(2);
        }

        $urlArgument = $argument;
    }
}

if ($urlArgument !== null) {
    crawlUrl($urlArgument, $arguments);
}

$siteFilter = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--site=')) {
        $siteFilter = substr($argument, strlen('--site='));
    }
}

$manifests = glob(dirname(__DIR__) . '/src/Adapters/*/manifest.json') ?: [];
sort($manifests);
$failures = 0;
$checked = 0;

foreach ($manifests as $manifestPath) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $slug = is_string($manifest['slug'] ?? null) ? $manifest['slug'] : '';
    if ($slug === '' || ($siteFilter !== null && $slug !== $siteFilter)) {
        continue;
    }

    $targets = is_array($manifest['runtime']['targetProfiles'] ?? null)
        ? $manifest['runtime']['targetProfiles']
        : [];
    $firstTarget = $targets === [] ? null : reset($targets);
    $url = is_array($firstTarget) && is_string($firstTarget['url'] ?? null)
        ? $firstTarget['url']
        : ($manifest['base_url'] ?? null);

    if (! is_string($url) || $url === '') {
        printf("FAIL %-16s no live target configured\n", $slug);
        ++$failures;
        continue;
    }

    ++$checked;
    $startedAt = hrtime(true);

    try {
        $builder = CrawlerX::url($url)->site($slug);
        if ($slug === 'javlibrary') {
            $builder = $builder->options(new CrawlOptionsDto(
                fetch: new FetchOptionsDto(method: FetchMethod::Flaresolverr, noFallback: true),
            ));
        } elseif (in_array($slug, ['javbtc', 'minnanoav'], true)) {
            $builder = $builder->options(new CrawlOptionsDto(
                fetch: new FetchOptionsDto(profile: FetchProfile::BrowserLikely),
            ));
        }

        if ($slug === 'onepondo') {
            $builder = $builder->type(CrawlType::Listing);
        }

        $result = $builder->crawl();
        $count = $result instanceof CrawlListResultDto ? count($result->items) : 1;
        $usable = $result instanceof CrawlListResultDto
            ? $count > 0
            : $result instanceof CrawlItemResultDto && itemHasIdentity($result);

        if (! $usable) {
            throw new RuntimeException('crawl returned no usable items');
        }

        printf(
            "PASS %-16s %4d item(s) %7.2fs %s\n",
            $slug,
            $count,
            (hrtime(true) - $startedAt) / 1_000_000_000,
            $url,
        );
    } catch (Throwable $exception) {
        ++$failures;
        printf(
            "FAIL %-16s %7.2fs %s -- %s\n",
            $slug,
            (hrtime(true) - $startedAt) / 1_000_000_000,
            $url,
            $exception->getMessage(),
        );
    } finally {
        CrawlerXFactory::reset();
    }
}

if ($checked === 0) {
    fwrite(STDERR, "No matching site manifest found.\n");
    exit(2);
}

printf("\nChecked %d site(s): %d passed, %d failed.\n", $checked, $checked - $failures, $failures);
exit($failures === 0 ? 0 : 1);

function itemHasIdentity(CrawlItemResultDto $item): bool
{
    $entity = $item->meta[$item->entityType] ?? null;
    if (! is_array($entity)) {
        return false;
    }

    foreach (['external_id', 'title', 'name'] as $key) {
        if (is_string($entity[$key] ?? null) && trim($entity[$key]) !== '') {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $arguments
 */
function crawlUrl(string $url, array $arguments): never
{
    $values = [];
    $headers = [];
    $flags = [];
    $knownValues = [
        'site',
        'type',
        'page',
        'fetch-profile',
        'fetch-method',
        'fetch-chain',
        'timeout',
    ];
    $knownFlags = ['try', 'no-fallback', 'verify-ssl', 'no-verify-ssl'];

    foreach ($arguments as $argument) {
        if ($argument === $url) {
            continue;
        }

        if (str_starts_with($argument, '--header=')) {
            $header = substr($argument, strlen('--header='));
            $separator = strpos($header, ':');
            if ($separator === false || trim(substr($header, 0, $separator)) === '') {
                failUsage("Invalid header: {$header}");
            }
            $headers[trim(substr($header, 0, $separator))] = trim(substr($header, $separator + 1));
            continue;
        }

        if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
            [$name, $value] = explode('=', substr($argument, 2), 2);
            if (! in_array($name, $knownValues, true) || $value === '') {
                failUsage("Unknown or empty option: {$argument}");
            }
            $values[$name] = $value;
            continue;
        }

        if (str_starts_with($argument, '--')) {
            $name = substr($argument, 2);
            if (! in_array($name, $knownFlags, true)) {
                failUsage("Unknown option: {$argument}");
            }
            $flags[$name] = true;
            continue;
        }

        failUsage("Unexpected argument: {$argument}");
    }

    if (isset($flags['verify-ssl'], $flags['no-verify-ssl'])) {
        failUsage('--verify-ssl and --no-verify-ssl cannot be combined.');
    }

    $type = isset($values['type']) ? CrawlType::tryFrom($values['type']) : null;
    if (isset($values['type']) && $type === null) {
        failUsage("Invalid crawl type: {$values['type']}");
    }

    $profile = isset($values['fetch-profile']) ? FetchProfile::tryFrom($values['fetch-profile']) : null;
    if (isset($values['fetch-profile']) && $profile === null) {
        failUsage("Invalid fetch profile: {$values['fetch-profile']}");
    }

    $method = isset($values['fetch-method']) ? FetchMethod::tryFrom($values['fetch-method']) : null;
    if (isset($values['fetch-method']) && $method === null) {
        failUsage("Invalid fetch method: {$values['fetch-method']}");
    }

    $chain = null;
    if (isset($values['fetch-chain'])) {
        $methods = [];
        foreach (explode(',', $values['fetch-chain']) as $methodName) {
            $chainMethod = FetchMethod::tryFrom(trim($methodName));
            if ($chainMethod === null) {
                failUsage("Invalid fetch-chain method: {$methodName}");
            }
            $methods[] = $chainMethod;
        }
        $chain = new FetchChainDto($methods);
    }

    $page = integerOption($values, 'page', minimum: 1);
    $timeout = integerOption($values, 'timeout', minimum: 1);
    $verifySsl = isset($flags['verify-ssl']) ? true : (isset($flags['no-verify-ssl']) ? false : null);
    $http = $timeout !== null || $verifySsl !== null || $headers !== []
        ? new HttpOptionsDto($timeout, $verifySsl, $headers === [] ? null : $headers)
        : null;
    $fetch = $profile !== null || $method !== null || $chain !== null || isset($flags['no-fallback'])
        ? new FetchOptionsDto($profile, $method, $chain, isset($flags['no-fallback']))
        : null;
    $options = $http !== null || $fetch !== null ? new CrawlOptionsDto($http, $fetch) : null;

    try {
        $builder = CrawlerX::url($url);
        if (isset($values['site'])) {
            $builder = $builder->site($values['site']);
        }
        if ($type !== null) {
            $builder = $builder->type($type);
        }
        if ($page !== null) {
            $builder = $builder->page($page);
        }
        if ($options !== null) {
            $builder = $builder->options($options);
        }

        $result = isset($flags['try']) ? $builder->tryCrawl() : $builder->crawl();
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("%s: %s\n", $exception::class, $exception->getMessage()));
        exit(1);
    } finally {
        CrawlerXFactory::reset();
    }
}

/**
 * @param array<string, string> $values
 */
function integerOption(array $values, string $name, int $minimum): ?int
{
    if (! isset($values[$name])) {
        return null;
    }

    $value = filter_var($values[$name], FILTER_VALIDATE_INT);
    if (! is_int($value) || $value < $minimum) {
        failUsage("--{$name} must be an integer greater than or equal to {$minimum}.");
    }

    return $value;
}

function failUsage(string $message): never
{
    fwrite(STDERR, $message . "\nRun with --help for usage.\n");
    exit(2);
}
