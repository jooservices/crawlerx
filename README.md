# jooservices/crawlerx

[![CI](https://github.com/jooservices/crawlerx/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/crawlerx/actions/workflows/ci.yml)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/crawlerx/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/crawlerx)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![GitHub Release](https://img.shields.io/github/v/release/jooservices/crawlerx?display_name=tag)](https://github.com/jooservices/crawlerx/releases)
[![Packagist Version](https://img.shields.io/packagist/v/jooservices/crawlerx)](https://packagist.org/packages/jooservices/crawlerx)
[![Total Downloads](https://img.shields.io/packagist/dt/jooservices/crawlerx)](https://packagist.org/packages/jooservices/crawlerx)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A PHP 8.5+ URL-driven crawl and parse library for JAV catalog sites. Give
CrawlerX a supported URL and it detects the site and page type, fetches the
page through the appropriate HTTP or browser strategy, and returns typed DTOs.

```php
use JOOservices\CrawlerX\CrawlerX;

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();

echo $item->entityType;                         // movie
echo $item->meta['movie']['external_id'];       // ymds282
echo $item->meta['movie']['code'];              // YMDS-282
```

> [!WARNING]
> This repository is a ground-up, framework-agnostic rebuild. The currently
> published Packagist `v1.0.0` is the retired Laravel implementation and does
> **not** provide the API documented here. Until this rebuild receives a new
> tagged release, install this checkout as a Composer path repository.

## Features

- One public builder API for listing, detail, performer-listing, and performer-detail pages
- Automatic site and crawl-type detection from the URL
- Typed immutable results built on `jooservices/dto` v3
- HTTP fetching through `jooservices/client` v4
- Adaptive fallback through curl-impersonate, Playwright, stealth browser modes, Puppeteer, and FlareSolverr
- Browser cookie handoff, challenge-page detection, and JavBus age-verification handling
- Pagination metadata and structured non-throwing errors
- Network-free CI tests backed by captured HTML fixtures
- Docker CLI for running the same public crawl flow against live URLs

## Requirements

- PHP `^8.5`
- PHP extensions: `dom`, `libxml`
- Composer
- Docker with Docker Compose for the recommended development and live-check workflow

Node.js is only required when running browser fetches directly on the host.
The Docker workflow supplies Node.js, Playwright Chromium, and FlareSolverr as
sidecar services.

## Installation

### Current development checkout

Place this repository next to the consuming application, then register it as a
Composer path repository:

```bash
composer config repositories.crawlerx path ../crawlerx
composer require jooservices/crawlerx:@dev
```

The path installation uses this checkout's actual requirements, including
`jooservices/client` v4 and `jooservices/dto` v3.

### Tagged release

After the rebuilt package receives a new release newer than the retired
`v1.0.0`, normal Composer installation will be:

```bash
composer require jooservices/crawlerx
```

For development inside this repository, build the PHP 8.5 tooling image and
install its dependencies:

```bash
make install
```

## Usage

### Automatic detection

`CrawlerX::url($url)->crawl()` is the normal entry point. The URL determines
the adapter and crawl type:

```php
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;

$list = CrawlerX::url('https://onejav.com/new')->crawl();
$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();

assert($list instanceof CrawlListResultDto);
assert($item instanceof CrawlItemResultDto);
```

### Explicit site, type, and page

Override detection when a URL is ambiguous or when the caller already knows
the desired operation:

```php
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Enums\CrawlType;

$result = CrawlerX::url('https://en.1pondo.tv/list/?o=n&page=2')
    ->site('onepondo')
    ->type(CrawlType::Listing)
    ->page(2)
    ->crawl();
```

Supported crawl types are `Listing`, `Detail`, `PerformerListing`, and
`PerformerDetail`.

### Result DTOs

`crawl()` returns only `CrawlItemResultDto` or `CrawlListResultDto`. Item roots
contain `url`, `entity_type`, and `meta`; movie data lives under `meta.movie`,
and performer data under `meta.performer`. Listings declare the same
`entity_type` and contain one entity per `items[]` entry. CrawlerX does not
create a `track_id`; correlation belongs to the consuming application.

A listing or performer-listing result also provides pagination:

```php
$list->pagination->currentPage;
$list->pagination->lastPage;
$list->pagination->nextPage;
$list->pagination->nextUrl;
$list->pagination->hasNextPage;
```

### Non-throwing crawl

Use `tryCrawl()` when failures should be returned as data instead of thrown:

```php
$outcome = CrawlerX::url($url)->tryCrawl();

if ($outcome->failed()) {
    echo $outcome->error?->code->value;
    echo $outcome->error?->message;
}
```

Error codes include `unsupported_url`, `ambiguous_url`, `adapter_not_found`,
`blocked`, `parse_failed`, and `unknown`.

### Fetch options

Each adapter has a default fetch profile. Override it per request when a
specific runtime strategy is required:

```php
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Enums\FetchMethod;

$item = CrawlerX::url('https://en.jable.tv/videos/fjin-091/')
    ->options(new CrawlOptionsDto(
        fetch: new FetchOptionsDto(
            method: FetchMethod::ChromeStealth,
            noFallback: true,
        ),
    ))
    ->crawl();
```

Available methods are `http`, `curl_impersonate`, `playwright`,
`playwright_stealth`, `chrome_stealth`, `puppeteer_stealth`, and
`flaresolverr`. Available profiles are `http_only`, `browser_likely`, and
`adaptive`.

## Supported sites

CrawlerX currently registers 32 adapters. Capabilities below come from each
adapter's current manifest.

| Site | Slug | Listing | Detail | Performer listing | Performer detail |
| --- | --- | :---: | :---: | :---: | :---: |
| 141Jav | `141jav` | Yes | Yes | — | — |
| 1Pondo | `onepondo` | Yes | Yes | — | — |
| 10musume | `10musume` | Yes | Yes | — | — |
| Avfan | `avfan` | Yes | Yes | — | — |
| Bstar | `bstar` | — | — | Yes | Yes |
| Caribbeancom | `caribbeancom` | Yes | Yes | — | — |
| DUGA | `duga` | Yes | Yes | — | — |
| FC2 Content Market | `fc2` | Yes | Yes | — | — |
| FFJav | `ffjav` | Yes | Yes | — | — |
| HEYZO | `heyzo` | Yes | Yes | — | — |
| IDEAPOCKET | `ideapocket` | Yes | Yes | — | — |
| Jable | `jable` | Yes | Yes | Yes | Yes |
| JAV Database | `javdatabase` | — | — | Yes | Yes |
| JavBTC | `javbtc` | Yes | Yes | — | — |
| JavBus | `javbus` | Yes | Yes | Yes | Yes |
| JavDB | `javdb` | Yes | Yes | — | — |
| JAVLibrary | `javlibrary` | Yes | Yes | Yes | Yes |
| Kin8tengoku | `kin8tengoku` | Yes | Yes | — | — |
| Madonna | `madonna` | Yes | Yes | — | — |
| Mine's | `mines` | — | — | Yes | Yes |
| Minnano AV | `minnanoav` | Yes | Yes | Yes | Yes |
| MissAV | `missav` | Yes | Yes | — | — |
| MOODYZ | `moodyz` | Yes | Yes | — | — |
| Muramura | `muramura` | Yes | Yes | — | — |
| OneJav | `onejav` | Yes | Yes | — | — |
| Pacopacomama | `pacopacomama` | Yes | Yes | — | — |
| S1 NO.1 STYLE | `s1` | Yes | Yes | — | — |
| SOFT ON DEMAND | `sod` | — | — | Yes | Yes |
| T-Powers | `tpowers` | — | — | Yes | Yes |
| Tokyo-Hot | `tokyohot` | Yes | Yes | — | — |
| Warashi | `warashi` | — | — | Yes | Yes |
| XCITY | `xcity` | Yes | Yes | Yes | Yes |

DUGA, Tokyo-Hot, Caribbeancom, HEYZO, and JavBus use accepted adult landing
routes when a root listing URL would otherwise return an age-verification page.

Live sites change independently of this package. A supported adapter means the
URL shape and parser are implemented; availability can still be affected by
site downtime, blocking, region restrictions, or DOM changes.

## Live crawl from the host through Docker

Start the browser helpers once:

```bash
docker compose --profile fetch up -d --wait node flaresolverr
```

Then crawl any URL. The command executes the same public builder flow and
prints the complete result DTO as JSON:

```bash
docker compose run --rm php php tools/live-check.php \
  'https://www.javbus.com/en/NAMH-074'
```

Pass builder options when explicit routing is required:

```bash
docker compose run --rm php php tools/live-check.php \
  'https://www.javbus.com/en/NAMH-074' \
  --site=javbus \
  --type=detail
```

That command is equivalent to:

```php
CrawlerX::url('https://www.javbus.com/en/NAMH-074')
    ->site('javbus')
    ->type(CrawlType::Detail)
    ->crawl();
```

Common CLI mappings:

| CLI argument | Public API equivalent |
| --- | --- |
| `--site=javbus` | `->site('javbus')` |
| `--type=detail` | `->type(CrawlType::Detail)` |
| `--page=2` | `->page(2)` |
| `--try` | `->tryCrawl()` |
| `--fetch-profile=browser_likely` | `FetchProfile::BrowserLikely` |
| `--fetch-method=flaresolverr --no-fallback` | FlareSolverr with fallback disabled |
| `--fetch-chain=playwright,chrome_stealth,flaresolverr` | Explicit ordered fetch chain |
| `--timeout=30` | `HttpOptionsDto(timeout: 30)` |
| `--header='Accept-Language: en-US'` | HTTP request header override |

Show every CLI option:

```bash
docker compose run --rm php php tools/live-check.php --help
```

Run one canonical target for every registered adapter, or only one adapter:

```bash
make live-check
make live-check SITE=--site=javbus
```

Stop and remove the browser sidecars when finished:

```bash
docker compose --profile fetch down
```

The PHP container owns CrawlerX execution and parsing. The `node` and
`flaresolverr` services are long-running fetch helpers used only when the
selected strategy requires a browser or challenge solver.

## Runtime configuration

The Docker Compose file configures the sidecar URLs automatically. Direct host
or custom-container integrations can use these environment variables:

| Variable | Purpose |
| --- | --- |
| `CRAWLERX_NODE` | Node.js executable; defaults to `node` |
| `CRAWLERX_PLAYWRIGHT_SCRIPT` | Path to `playwright-fetch.mjs` |
| `CRAWLERX_PUPPETEER_SCRIPT` | Path to `puppeteer-stealth-fetch.mjs` |
| `CRAWLERX_CURL_IMPERSONATE` | curl-impersonate executable |
| `CRAWLERX_BROWSER_SERVICE_URL` | Remote Node browser service base URL |
| `CRAWLERX_FLARESOLVERR_URL` | FlareSolverr API endpoint |

## Testing

PHP tooling runs on the host PHP directly when it satisfies `^8.5`, and falls
back to `php:8.5-cli-bookworm` through Docker Compose otherwise. Docker
remains required for the Node/Playwright/FlareSolverr fetch sidecars
regardless of the host PHP version.

```bash
make build
make install
make test
make ci
```

`make test` runs the deterministic PHPUnit suites. `make ci` runs lint, static
analysis, both coverage suites, and the 85% coverage checks.

| Command | Purpose |
| --- | --- |
| `make build` | Build the PHP 8.5 Docker image (skipped when host PHP matches `^8.5`) |
| `make install` | Run `composer install` on the host, or inside Docker as a fallback |
| `make shell` | Open an interactive shell in the PHP container |
| `make validate` | Run `composer validate --strict` |
| `make lint` | Run Pint, PHPCS, PHPStan, PHPMD, and PHP-CS-Fixer |
| `make test` | Run all PHPUnit suites without coverage |
| `make test-coverage` | Generate Unit and Feature Clover reports |
| `make audit` | Run Composer's dependency security audit |
| `make ci` | Run lint, coverage suites, and coverage enforcement |
| `make fetch-up` | Start and health-check Node and FlareSolverr |
| `make live-check` | Crawl one live target for every adapter |
| `make fetch-down` | Stop and remove live-fetch sidecars |

### Fixture-based tests

CI never performs live HTTP requests. Tests use `ClientBuilder::fake()` and
captured HTML under `tests/Fixtures/`:

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\CrawlerX\CrawlerX;
use JOOservices\CrawlerX\Tests\Support\FixtureResponder;

ClientBuilder::fake();
FixtureResponder::for('GET', 'https://onejav.com/torrent/ymds282')
    ->file('onejav/detail-sample-1.html');

$result = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
```

When the client is faked, the fetch plan is intentionally restricted to HTTP,
so tests cannot launch Playwright, Puppeteer, or FlareSolverr accidentally.

Refresh the configured live fixtures from the host when a site's DOM changes:

```bash
make fixtures
```

Fixture refresh uses the URLs declared in adapter manifests. Review captured
HTML before committing it and keep live network access out of CI.

## Architecture

```text
CrawlerX facade
    -> immutable CrawlRequestBuilder
    -> URL/site/type detection
    -> fetch-plan selection and fallback chain
    -> site adapter parser
    -> CrawlItemResultDto or CrawlListResultDto
```

Adapter behavior and supported page types are declared under
`src/Adapters/*/manifest.json`. `CrawlerXFactory::reset()` is available for
long-running workers that need to release the process-wide runtime instance.

## Documentation

- [Changelog](CHANGELOG.md)
- [Fixture capture guide](tools/fixtures/README.md)
- [GitHub Actions workflows](WORKFLOWS.md)
- [Contributing guide](CONTRIBUTING.md)

## Development

Use the verified commands in the [Testing](#testing) section before opening a
pull request. The branch model, local hooks, and review requirements are
defined in [CONTRIBUTING.md](CONTRIBUTING.md); the remote checks are documented
in [WORKFLOWS.md](WORKFLOWS.md).

## Community

- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Support](SUPPORT.md)
- [Governance](GOVERNANCE.md)

## License

MIT — see [LICENSE](LICENSE).
