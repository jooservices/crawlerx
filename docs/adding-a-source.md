# Adding a source

A new source touches only its own folders plus one CHANGELOG line. No shared test or doc needs an edit: the shared tests and the adapter-count assertions read `src/Adapters/*/manifest.json`.

1. Create `src/Adapters/<Site>/` with the adapter, its types and `manifest.json` (copy a similar adapter's manifest).
2. Capture fixtures under `tests/Fixtures/<slug>/` (see [tools/fixtures/README.md](../tools/fixtures/README.md)) and add adapter tests under `tests/Unit/Adapters/<Site>/`.
3. List every fixture in `runtime.fixtureSamples` (`url`, `name`, `type`). `AllAdaptersCrawlTest` and `ManifestFixtureExecutorTest` crawl each non-`supporting`/`taxonomy` sample through its fixture; `supporting` and `taxonomy` samples are only registered as extra responses.
4. If crawling a sample requests other pages (for example a date index that links to a date-listing page), declare them on that sample:

   ```json
   {
       "url": "https://example.test/works/date",
       "name": "dates.html",
       "type": "listing",
       "followUps": [
           { "url": "https://example.test/works/list/date/2026-12-01", "name": "list-2026-12-01.html" }
       ]
   }
   ```

   Each follow-up is served from `tests/Fixtures/<slug>/<name>`.
5. Add one line under `[Unreleased]` in `CHANGELOG.md`.

The README site table and the counts are not hand-maintained per site; the registry-count tests compare the registry with the number of manifest files, so a manifest that fails to register fails the build.
