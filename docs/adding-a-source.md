# Adding a source

A new source touches only its own folders plus one CHANGELOG line. No shared test or doc needs an edit: the shared tests and the adapter-count assertions read `src/Adapters/*/manifest.json`.

1. Create `src/Adapters/<Site>/` with the adapter, its types and `manifest.json` (copy a similar adapter's manifest).
2. Capture fixtures under `tests/Fixtures/<slug>/` (see [tools/fixtures/README.md](../tools/fixtures/README.md)) and add adapter tests under `tests/Unit/Adapters/<Site>/`.
3. List every fixture in `runtime.fixtureSamples` (`url`, `name`, `type`). `AllAdaptersCrawlTest` and `ManifestFixtureExecutorTest` crawl each non-`supporting`/`taxonomy` sample through its fixture; `supporting` and `taxonomy` samples are only registered as extra responses. A sample whose fixture file is missing is skipped by the shared tests, so check that every `name` matches a file.
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
6. Add the site's row to the supported-sites table in `README.md` (a documentation step; no test depends on it).

No count is hand-maintained per site; the registry-count tests compare the registry with the number of manifest files, so a manifest that fails to register fails the build.

## Shared parsing helpers

Do not hand-write the same private helper in a new adapter; compose the traits under `src/Adapters/Concerns/`:

| Trait | Gives you |
|---|---|
| `ParsesHtml` | `firstText`, `firstAttribute`, `texts`, `normalizeText` for a DomCrawler node |
| `NormalizesUrls` | `absolute($baseUrl, $url)` resolving relative, protocol-relative and root-relative links |
| `ResolvesLinkUrls` | `linkUrl` (compose with `ParsesHtml` + `NormalizesUrls`) for an absolute href from a CSS selector |
| `MatchesUrlPath`, `ReadsLastPathSegment`, `ReadsPathNumbers` | `pathMatch`, `lastPathSegment`, `pathNumber`, `pathInteger` for external ids and page numbers in the URL path (adapters keep their own route regex) |
| `ReadsQueryParams`, `ReadsQueryNumbers`, `ReadsPathOrQueryParam` | `queryParam`, `hasQueryParam`, `firstQueryParam`, `queryNumber`, `positiveNumericQueryNumber`, `queryPageNumber`, `pathMatchOrQueryParam` for query-string values |
| `CoercesStrings`, `CoercesNumbers` | `nonEmptyString`; `positiveInt`, `pageNumberFromText`, `integerStat` for loosely typed scraped values |
| `CleansText`, `StripsTitleCode`, `ParsesDateStrings`, `ReadsArrayValues` | `cleanText`, `cleanTextWithoutTrailingPhrase`; `titleWithoutCode`; `dateString`; `arrayValue` |
| `ParsesJson` | JSON response decoding for API-backed sources |

A Type extends `AbstractHtmlType` (HTML pages fetched through the shared client) or `AbstractType` (API/JSON or custom fetching); do not introduce a third base class. If a helper you need exists in two adapters, move it into a shared concern with a test instead of copying it.

**One concern, one responsibility.** A new shared helper is a NEW file under `src/Adapters/Concerns/` with its own unit test in `tests/Unit/Adapters/Concerns/`; do not append unrelated helpers to an existing concern (it is how the old grab-bag traits became merge-conflict magnets). Keep each concern file under about 60 lines, and have an adapter `use` only the concerns it calls. A concern that builds on another declares it with `use` inside the trait.
