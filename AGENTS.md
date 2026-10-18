# CrawlerX

Follow workspace [`AGENTS.md`](../AGENTS.md). Canonical policy is at the JOOservices workspace root.

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

## Public API

```php
use JOOservices\CrawlerX\CrawlerX;

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
```

## Testing

Tests use `ClientBuilder::fake()` with HTML fixtures under `tests/Fixtures/`. No live HTTP in CI.
