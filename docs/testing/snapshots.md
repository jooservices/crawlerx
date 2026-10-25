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


The AVfanProfiles current sample is a minimal sanitized DOM excerpt: personal
values and image addresses are replaced with synthetic data. The former sample
now returns an HTTP-200 Japanese soft-404. Its historical positive response is
retained as `detail-arisu-mai.txt` for the existing detailed parser regression
assertions, separately from the current manifest captures. The minimal negative
response is `detail-removed.txt` and tests fail-fast classification without
launching a browser.
