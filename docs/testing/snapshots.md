# CrawlerX sanitized snapshots

`tools/fixtures/capture.mjs` captures a manifest sample through HTTP,
Playwright, Puppeteer, or FlareSolverr and writes the HTML plus provenance
metadata under `tests/Fixtures/<site>/`. Captures are sanitized before they
replace a fixture.

Capture one adapter:

```sh
node tools/fixtures/capture.mjs --manifest --site=avfan
```

Capture an explicit URL:

```sh
node tools/fixtures/capture.mjs \
  --site=avfan \
  --url='https://avfan.com/en' \
  --out=tests/Fixtures/Avfan/listing-placeholder.html \
  --type=listing
```

The sanitizer removes configured cookie values, token and CSRF values,
`Set-Cookie` echoes, account attributes, and email addresses. `--no-sanitize`
is reserved for local diagnosis and must not be used to create a committed
fixture. Snapshot tests parse the stored HTML offline; they do not read `.env`
and do not make network calls.

Run the offline checks with:

```sh
vendor/bin/phpunit tests/Feature/Snapshots
node --test tests/Unit/Tools/sanitizer.test.mjs
```

