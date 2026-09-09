# CrawlerX

Follow workspace [`AGENTS.md`](../AGENTS.md). Canonical policy is at the JOOservices workspace root.

PHP `^8.5` crawl/parse library for JAV catalog sites. Uses `jooservices/client` v4 and `jooservices/dto` v3.

## Docker workflow

```bash
make build
make install
make lint
make test
make ci
```

All PHP commands run inside Docker (`php:8.5-cli-bookworm`).

## Public API

```php
use JOOservices\CrawlerX\CrawlerX;

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
```

## Testing

Tests use `ClientBuilder::fake()` with HTML fixtures under `tests/Fixtures/`. No live HTTP in CI.
