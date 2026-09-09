# Fixture capture (dev only)

PHPUnit tests read static HTML/JSON from `tests/Fixtures/`. CI never hits live sites.

Capture **real DOM HTML** with Playwright:

```bash
make fixtures
# or one URL:
node tools/fixtures/capture.mjs \
  --url='https://en.jable.tv/videos/fjin-091/' \
  --out=tests/Fixtures/jable/detail-fjin-091.html

# For a Cloudflare-protected source with the fetch sidecars running:
node tools/fixtures/capture.mjs \
  --flaresolverr-url=http://127.0.0.1:8191/v1 \
  --url='https://www.javlibrary.com/en/vl_newrelease.php' \
  --out=tests/Fixtures/JavLibrary/listing-live.html
```

`CrawlerX::url()->crawl()` uses the in-package fetch chain (HTTP → curl-impersonate → Playwright → stealth → chrome-stealth → puppeteer → FlareSolverr). Tests stub that chain with `ClientBuilder::fake()` so CI stays offline.
