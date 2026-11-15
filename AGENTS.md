# CrawlerX

Follow workspace [`AGENTS.md`](../AGENTS.md). Canonical policy is at the JOOservices workspace root.

## Engineering standard

Every change follows the workspace standard [`standards/engineering-principles.md`](../../../standards/engineering-principles.md): SOLID, KISS, YAGNI and DRY, the hard gates (cyclomatic complexity at most 10, no duplicated block of 100 tokens or more, no unused code, no suppression comments, full line and branch coverage of the code you write or change) and the test policy. The project profile is [`.ai/project.json`](.ai/project.json); tickets, developers and reviewers go through the workspace tools (`scripts/dev/assign`, `scripts/dev/develop`, `scripts/dev/check quick|final`, `scripts/git`).

PHP `^8.5` crawl/parse library for JAV catalog sites. Uses `jooservices/client` v4 and `jooservices/dto` v3.

## Configuration and runtime

Everything is configured through `.env` (see `.env.example`): `PLAYWRIGHT_URL`, `FLARESOLVERR_URL`, `CRAWLERX_PHP`, `CRAWLERX_*`. Nothing is hard-wired to Docker. In Workspaces the defaults are the shared PHP runtime (`CRAWLERX_PHP=../../../scripts/docker/runtime php@8.5`) and the Dev VM services (`scripts/docker/playwright-fetch-vm up`, `scripts/docker/flaresolverr-vm up`); do not start this project's own Docker services unless `.env` points at them.

## Docker workflow (optional)

```bash
make build
make install
make lint
make test
make ci
```

Without `CRAWLERX_PHP`, PHP commands run inside this project's Docker (`php:8.5-cli-bookworm`).

## Project commands

| Command | Purpose |
|---|---|
| `composer lint` | Pint, PHPCS, PHPStan (max), PHPMD, PHP-CS-Fixer |
| `composer lint:deptrac` | Layer rules (Adapters, Fetch, Dto, Support, Contracts) |
| `composer check:manifests`, `check:changelog`, `check:fixtures-meta`, `check:readme-sites` | Repository consistency checks |
| `composer test:changed` | Tests for the files changed against `origin/develop` |
| `php -d pcov.enabled=1 vendor/bin/phpunit` | Full suite (needs a coverage driver) |

## Public API

```php
use JOOservices\CrawlerX\CrawlerX;

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
```

## Testing

Tests use `ClientBuilder::fake()` with HTML fixtures under `tests/Fixtures/`. No live HTTP in CI.
