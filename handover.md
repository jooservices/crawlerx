# CrawlerX handover

**Package:** `jooservices/crawlerx`  
**Path:** `/Users/vietvu/Sites/JOOservices/crawlerx`  
**Handover date:** 2026-08-28 (fixture/runtime audit refresh: 2026-09-08)  
**Audience:** next developer continuing this work. Git is **not** required.

> Numbers below (test counts, coverage, adapter count) were re-verified on
> 2026-09-08 and no longer match the original 2026-08-28 handover. Trust the
> 2026-09-08 notes where the two disagree.

This file is the working contract. `knowledge.md` is older research; parts of it (Laravel, a split fetch package) are **rejected**. Trust this file over `knowledge.md`.

---

## 1. What this package is

A **pure PHP 8.5 library**. No Laravel. No separate fetch package.

Caller gives a URL. The library:

1. Detects site + crawl type + page from the URL
2. Fetches HTML/JSON (HTTP, then escalate to browser if the site needs it)
3. Parses with the matching site adapter
4. Returns `CrawlItemResultDto` or `CrawlListResultDto`

Happy path:

```php
use JOOservices\CrawlerX\CrawlerX;

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
$list = CrawlerX::url('https://onejav.com/new')->crawl();
```

Overrides:

```php
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\FetchOptionsDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;
use JOOservices\CrawlerX\Enums\CrawlType;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Enums\FetchProfile;

CrawlerX::site('onejav')->url($url)->crawl();
CrawlerX::url($url)->type(CrawlType::Detail)->page(2)->crawl();
CrawlerX::url($url)->options(new CrawlOptionsDto(
    http: new HttpOptionsDto(timeout: 90),
    fetch: new FetchOptionsDto(method: FetchMethod::ChromeStealth, noFallback: true),
))->crawl();

$outcome = CrawlerX::url($url)->tryCrawl(); // soft-fail DTO, does not throw
```

**Owner expectation (explicit):** crawlerx handles **everything** needed to crawl, including Playwright, stealth, chrome-stealth, curl-impersonate, FlareSolverr. Adapters never import a browser.

---

## 2. Why (decisions the next person must not undo)

| Decision | Why |
|----------|-----|
| One package, not a fetch companion | Product is “give URL, get DTO”. Splitting fetch was a research note, then used as an excuse to ship a parser. Rejected. |
| No Laravel | Sibling packages `client` / `dto` are standalone. This one is too. |
| Fetch above adapters | Adapters parse. Orchestrator fetches, then seeds `CrawlHttpClient`. Same parse path in tests and production. |
| Cheapest fetch first | HTTP sites never launch Chromium. Browser-likely sites skip doomed HTTP. |
| `ClientBuilder::fake()` in CI | No live HTTP in GitHub Actions. When fake is on, fetch plan is HTTP-only. |
| Real fixtures | HTML/JSON under `tests/Fixtures/` must come from a live site (Playwright or curl). No invented markup. |
| Honest coverage | Feature suite must **not** include `tests/Unit`. 85% Unit **and** 85% Feature, separately. |
| PHP 8.5 + client v4 + dto v3 | Workspace standard. |
| Git later | Owner said git is not important. Repo is `develop`, often uncommitted. |

`knowledge.md` still talks about Laravel and a companion fetch package. Ignore those two. Keep the rest (DTO shapes, fetch order, site list, challenge detection).

---

## 3. How it works (runtime)

```
CrawlerX::url($url)->crawl()
  → CrawlRequestBuilder
  → CrawlOrchestrator
       UrlClassifier          host/path → slug + CrawlType + page
       SiteProfileDto::fromManifest()
       FetchPlanResolver      site plan + FetchOptionsDto + fake mode
       FetchFallbackChain     try methods until usable body
       seed FetchResultDto onto CrawlRequestDto
  → CrawlerXService::site($slug)->crawl($request)
       AdapterExecutor → site adapter Type
       CrawlHttpClient::get() → SeededCrawlHttpClient if fetch is set
  → CrawlItemResultDto | CrawlListResultDto
```

On failure, `crawl()` throws. `tryCrawl()` returns `CrawlOutcomeDto`.

### 3.1 Fetch plan

From `src/Adapters/{Site}/manifest.json` flag `runtime.playwrightFetchEnabled`:

| Profile | Sites | Default chain |
|---------|--------|----------------|
| `http_only` | onejav, onefouronejav, ffjav, xcity, warashi, onepondo, minnanoav, javbtc, javdatabase, javbus | `[http]` |
| `browser_likely` | jable, missav, javlibrary, avfan | `[playwright, playwright_stealth, chrome_stealth, puppeteer_stealth, flaresolverr]` |

`FetchPlanResolver` precedence (highest wins):

1. `ClientBuilder::isFaked()` → always `[http]`
2. `FetchOptionsDto.chain` → exact list
3. `FetchOptionsDto.method` + `noFallback: true` → that method only
4. `FetchOptionsDto.method` + `noFallback: false` → start at method, then rest of base chain
5. `FetchOptionsDto.profile` → named preset (`http_only` / `browser_likely` / `adaptive`)
6. Site profile default

### 3.2 Fetch methods

| Method | Class | Needs |
|--------|--------|--------|
| `http` | `HttpFetchHandler` | `jooservices/client` v4 |
| `curl_impersonate` | `CurlImpersonateFetchHandler` | binary in PATH or `CRAWLERX_CURL_IMPERSONATE` |
| `playwright` | `PlaywrightFamilyFetchHandler` | Node + `scripts/playwright-fetch.mjs` (`stealthLevel: minimal`) |
| `playwright_stealth` | same | `stealthLevel: enhanced` |
| `chrome_stealth` | same | `headless: false` |
| `puppeteer_stealth` | `PuppeteerStealthFetchHandler` | `scripts/puppeteer-stealth-fetch.mjs` + puppeteer-extra (optional) |
| `flaresolverr` | `FlaresolverrFetchHandler` | `CRAWLERX_FLARESOLVERR_URL` POST `/v1` |

A method **fails** (next tier) on: HTTP 403/503, CF challenge body/title, empty/unusable body, timeout, missing binary/script, sidecar `challenge: true`.

Challenge detector (`src/Fetch/ChallengeDetector.php`):

- Title `Just a moment` / `Attention Required`
- `cf-browser-verification`
- Header `cf-mitigated: challenge`
- Small body with `Just a moment...` **and** `challenges.cloudflare.com`

Do **not** treat `challenges.cloudflare.com` alone as a challenge. Real javdatabase pages embed Turnstile scripts.

### 3.3 Seed into parse

`AbstractBaseCrawler::refreshClient()` wraps the real client:

```
SeededCrawlHttpClient(inner HTTP, FetchResultDto)
```

- Request URL (or `finalUrl`) → seeded body
- Other URLs (e.g. OnePondo gallery JSON) → inner client
- If inner is `ClientBuilder::fake()` and the URL is unregistered → 404 empty (so optional secondary fetches do not explode CI)

### 3.4 Env

| Env | Meaning |
|-----|---------|
| `CRAWLERX_NODE` | Node binary (default `node`) |
| `CRAWLERX_PLAYWRIGHT_SCRIPT` | Default `scripts/playwright-fetch.mjs` |
| `CRAWLERX_PUPPETEER_SCRIPT` | Default `scripts/puppeteer-stealth-fetch.mjs` |
| `CRAWLERX_CURL_IMPERSONATE` | curl-impersonate binary |
| `CRAWLERX_FLARESOLVERR_URL` | FlareSolverr endpoint |

### 3.5 Key classes

```
src/CrawlerX.php
src/CrawlerXFactory.php
src/Services/CrawlOrchestrator.php
src/Services/CrawlRequestBuilder.php
src/Services/CrawlerXService.php
src/Services/UrlClassifier.php
src/Services/AdapterExecutor.php
src/Fetch/FetchFallbackChain.php
src/Fetch/FetchPlanResolver.php
src/Fetch/Handlers/*
src/Http/SeededCrawlHttpClient.php
src/Http/ClientCrawlHttpClient.php
src/Adapters/{Site}/
src/Dto/Crawl*.php, Fetch*.php, SiteProfileDto.php
src/Enums/FetchMethod.php, FetchProfile.php, CrawlType.php, CrawlErrorCode.php
```

Inject a fake chain in tests: `CrawlerXFactory::useFetchChain($chain)` then `CrawlerXFactory::reset()` in teardown.

---

## 4. Requirements

### 4.1 Product

- PHP `^8.5`. `make` runs PHP tooling on the **host** PHP when it matches
  `^8.5` (verified via `php -r 'echo PHP_VERSION;'`) and falls back to
  `php:8.5-cli-bookworm` in Docker only when it does not. Docker stays
  mandatory for the Playwright/FlareSolverr sidecars (`make fetch-up`).
- `jooservices/client` `^4.0`, `jooservices/dto` `^3.0`
- Symfony DomCrawler + css-selector
- 20 site adapters (grew from the original 14): onejav, onefouronejav, ffjav,
  xcity, warashi, onepondo, javbtc, javlibrary, javdatabase, javbus, jable,
  missav, minnanoav, avfan, duga, fc2, heyzo, tokyohot, javdb, caribbeancom
- URL-only DX; optional `site` / `type` / `page` / `options`
- Fetch **in this repo**, including Playwright/stealth/chrome-stealth
- No Laravel, no live HTTP in CI

### 4.2 Quality

```bash
make build
make install
make lint    # Pint, PHPCS, PHPStan max, PHPMD, PHP-CS-Fixer
make test
make ci      # lint + Unit/Feature coverage + 85% floor each
```

- PHPStan: `phpstan.neon` **must not** include a baseline
- PHPMD: **must include** `src/Adapters` (no path exclude)
- PHPUnit Feature directory = `tests/Feature` only
- Coverage floor 85% on **Unit** and **Feature** separately (`tools/coverage-enforce.php`)
- Tests: `ClientBuilder::fake()` + fixtures. Fetch handlers: stub `ProcessRunner`, do not launch Chrome in CI

### 4.3 Fixtures

- Capture live DOM/JSON. No synthetic HTML.
- Playwright for HTML; curl for JSON APIs (onepondo).
- `make fixtures` or `node tools/fixtures/capture.mjs --url=... --out=...`
- If capture is a CF/age wall, keep that file as a **blocked** fixture (tests must expect `CrawlBlockedException` / `CrawlParseException`). Do not invent replacement markup.
- Capture script must **not overwrite** a good fixture with a challenge page (this is currently a footgun — fix it).

### 4.4 Out of scope

- Persistence, queues, Laravel, admin UI
- Git commit/push/PR unless the owner asks
- Live HTTP in GitHub Actions
- A second Composer package for fetch

---

## 5. Definition of done

Done only when **all** of these are true:

1. `CrawlerX::url($url)->crawl()` works for all 14 sites in the real world: HTTP sites via client v4; jable/missav/javlibrary/avfan via Playwright chain (or later tiers) without the caller configuring fetch.
2. Per-call `FetchOptionsDto` overrides work (`profile`, `method`, `chain`, `noFallback`).
3. `CrawlerX::site()`, `tryCrawl()`, typed `CrawlOptionsDto` on the builder.
4. Adapters parse **real** captured HTML/JSON. Manifest samples have no skip list.
5. JavLibrary, MissAV, JavBus have **usable** listing/detail fixtures (not CF interstitial, not javbus `driver-verify` wall) **or** production fetch actually bypasses those walls so a later capture succeeds.
6. `make ci` green: lint clean, 85% Unit, 85% Feature, Feature suite does not include Unit.
7. PHPStan max with **empty/no** baseline. PHPMD runs on adapters.
8. Docker PHP for CI; optional Node/Playwright compose profile for local crawl/capture.
9. Owner can use the library as a crawler, not only as a parser.

**Status as of 2026-09-08:** item 6 (coverage) is now met — `make ci` is green
with Unit 86.49% / Feature 85.71%, lint clean (Pint, PHPCS, PHPStan max,
PHPMD, PHP-CS-Fixer). Items 1 and 5 (JavBus/JavLibrary/MissAV walled sites)
were **not re-verified live** in this pass — this session only replaced
fixtures that were confirmed fabricated by content inspection (see §6.4); it
did not re-probe javbus.com / javlibrary.com / missav for whether the
CF/captcha walls documented on 2026-08-28 still apply. Re-check before
relying on that section.

---

## 6. What is done

### 6.1 Fetch + API (this package)

- Orchestrator fetch → seed → parse
- Full handler set listed in §3.2
- Playwright sidecar with stealth levels, age-gate click, cookies
- Cookie handoff store after browser success (in-memory)
- Typed options, `CrawlerX::site()`, real exception types
- Fake mode: HTTP-only plan so Feature tests stay offline

### 6.2 Lint / test hygiene (vs Cursor shortcuts)

| Shortcut that was wrong | Now |
|-------------------------|-----|
| PHPStan ~45 errors baselined | Baseline **removed**. `phpstan.neon` has no `phpstan-baseline.neon` include. Last run: clean. |
| PHPMD excluded `src/Adapters` | Adapters included. Codesize/StaticAccess relaxed like `jooservices/client`. |
| Feature suite included `tests/Unit` | Feature = `tests/Feature` only. |
| Combined clover double-counted | Still merges for Codecov, but enforce runs **per suite file**. |
| 8 manifest samples skipped | `ManifestFixtureCatalog::SKIP_SAMPLES` is **empty**. Blocked captures assert throw. |
| Avfan synthetic HTML | Replaced with live listing + detail (`ewTlM3Fj`). |
| `CrawlerX::site()` missing | Implemented. Classifier still detects type when site is forced. |

### 6.3 Tests (last green run, no coverage — 2026-09-08)

- Unit **388** tests, Feature **103** tests, **491** total, all passing
- `make ci`: Unit coverage 86.49%, Feature coverage 85.71% (both over the 85% floor)
- Fetch unit tests: plan resolver, fallback chain, Playwright handler (stubbed process), challenge detector
- Feature: `CrawlerX::url()` / `site()` / `tryCrawl()` / all remaining manifest samples

### 6.4 Fixtures (live capture, 2026-08-28)

Playwright (`scripts/playwright-fetch.mjs`) or curl. Report: `tools/fixtures/capture-report.json` if present.

| Site | Listing/detail capture | Notes |
|------|------------------------|--------|
| onejav | Real HTML | Recaptured listing + `ymds282` detail |
| 141jav | Real HTML | |
| ffjav | Real HTML | Detail recaptured as `mida-768` (manifest URL updated) |
| jable | Real HTML | listing + `fjin-091` |
| avfan | Real HTML | listing + `detail-ewTlM3Fj.html` (age overlay still in listing DOM; movie links present) |
| xcity | Real HTML | listing + avod detail |
| onepondo | Real **JSON via curl** | Playwright had saved HTML for JSON URLs — recaptured with curl. Gallery is a secondary fetch, not a manifest sample |
| javdatabase | Real HTML | |
| javbtc | Real HTML | |
| minnanoav | Real HTML | Manifest sample type is `performer_listing` (actress list is not movie listing) |
| warashi | Real HTML | Tests loosened to structure, not old synthetic names |
| **javbus** | **Age/captcha wall** (`driver-verify`, ~21KB) | Not a listing. Tests expect parse/block failure |
| **javlibrary** | **CF “Just a moment”** | Tests expect `CrawlBlockedException` |
| **missav.to** | **CF “Just a moment”** | Same. `missav.ws` / `missav.ai` returned real-looking HTML in a probe (status 404 in sidecar JSON — re-check before switching hosts) |

`tests/Fixtures/missav/detail.html` is still an **old synthetic** page used only for parser unit tests. Replace when a live detail capture exists.

### 6.5 Docker / make

- PHP image: `Dockerfile` (`php:8.5-cli-bookworm` + pcov + composer)
- `docker-compose.yml` service `node` (Playwright image), profile `fetch`
- `make fixtures` = npm + playwright chromium + manifest capture
- `package.json` at repo root for Playwright (dev capture only, not a PHP dependency)

---

## 7. What is left (do this, in order)

### P0 — Honest 85% coverage (`make ci` blocker) — DONE (2026-09-08)

`make ci` is green: Unit 86.49%, Feature 85.71%. No Unit tests were moved into
the Feature suite to get there.

### P0b — Fixture authenticity audit — DONE (2026-09-08)

An audit found several fixtures that were hand-authored instead of captured,
and one real bug that the fabricated fixtures had been masking:

- `minnanoav/av159081.html` + `filmography_page1.html` were fabricated
  ("SSIS-001 Sample Title One"). Replaced with a real capture of actress
  945093's filmography page 1 and her first real listed movie
  (`av570850.html`). Test assertions in `MinnanoAvAdapterTest` and
  `ExtendedAdapterCrawlTest` now match the real parsed values.
- `jable/detail-abf-359.html`, `detail-mudr-369.html`, `detail-hmn-862.html`,
  `listing-new-release-page2.html`, `listing-new-release-last-page.html` were
  fabricated. Replaced with real Playwright captures
  (`detail-dsod-031.html`, `detail-royd-348.html`, `detail-abf-382.html`,
  a real `new-release/2/`, and the real current last page `new-release/1625/`
  — the site's total page count moves over time; re-derive it from the
  pagination markup on `new-release/` before recapturing again, don't assume
  1625 stays current).
- **Real bug found while recapturing jable page 2**: `Jable\Types\Listing::pageFromUrl()`
  built its "sibling page" regex from the *current request URL* instead of the
  listing's root path. That happened to work at page 1 (request URL == root)
  and at the true last page (no bug-observable effect), but silently returned
  `hasNextPage: false` for every other real mid-listing page — jable.tv's real
  pagination markup has no `aria-label="next"`, only numbered page links, so
  production pagination was broken for any page beyond the first. The
  original fake `listing-new-release-page2.html` fixture had a hand-added
  `aria-label="Next Page"` link that isn't present on the real site, which is
  exactly what hid this from `make ci`. Fixed in `pageFromUrl()`: it now
  strips a trailing `/\d+` page segment from the request URL before matching
  sibling links. Covered by `ListingTest::test_listing_page_two_advances_pagination`.
- Removed `src/Adapters/*/fixtures/` entirely (44 files). It was a second,
  stale copy of `tests/Fixtures/`, never read by any test or runtime code
  (`ManifestFixtureCatalog::fixturePath()` only resolves under
  `tests/Fixtures/`), but shipped inside `src/` — meaning it was published to
  every Composer install of this package. Some of its files were still the
  fabricated stand-ins from initial scaffolding.
- Regenerated `.meta.json` provenance for 20 fixtures that were real captures
  but still labeled `"sanitized": true` / `"content_hash": "synthetic"` from
  the original scaffold (stale metadata, not stale content — verified each by
  reading the actual file). Several of these (the JavBus and JavLibrary
  4-file sets) are deliberately-kept real captures of the site's
  age/captcha-wall or Cloudflare challenge page, used to test the
  block-detection path — not movie pages, by design.
- Deleted two orphaned files with no test reference at all:
  `missav/detail.html` (fabricated, unused) and `missav/latest-page1.html`
  (real but superseded by `listing-new.html`, unused).

Not done in this pass: a similar authenticity sweep of `Javbtc`'s two-arg
`pageFromUrl($url, $listingUrl)` (same signature shape as Jable's, not
verified to have or lack the same bug) and the single-arg `pageFromUrl()`
variants in Avfan/JavBus/Xcity/MinnanoAv/JavLibrary/Missav — none were flagged
by the audit and all currently pass against real fixtures, but they weren't
specifically checked for the "request URL used as root" mistake.

### P1 — Usable fixtures for the three walled sites

1. **MissAV**  
   Probe: `https://missav.ws/latest-updates` returned a real title (`MissAV | 免費高清AV在線看`). Confirm host, recapture listing + one detail, update missav URL-detect hosts if the public host is no longer `missav.to`.

2. **JavLibrary**  
   Headed Playwright (`headless: false` / chrome-stealth) or FlareSolverr. If still CF, keep challenge fixtures and treat production as “needs browser session”. Do not paste synthetic listings.

3. **JavBus**  
   Wall is captcha (`#verifycode`), not a simple 18+ button. Need `storageState` after a human/FlareSolverr solve, then recapture listing/detail/stars. Playwright script already tries age clicks + `over18` cookies; that is **not** enough for javbus.

**Capture footgun:** `tools/fixtures/capture-all.mjs` wrote challenge HTML over previous files. Change it to refuse overwrite when `challenge === true` or title is `Just a moment`.

### P2 — Production crawl of browser sites

- Local: `npx playwright install chromium`, then `CrawlerX::url('https://en.jable.tv/videos/fjin-091/')->crawl()` **without** fake
- Optional: FlareSolverr via compose + `CRAWLERX_FLARESOLVERR_URL`
- Optional: curl-impersonate binary for M1
- Chrome-stealth needs a display (or Xvfb in the node image)

### P3 — Small correctness leftovers

- OnePondo: listing adapter requests constructed JSON URLs (`list_{kind}_{offset}.json`). Seeded client only matches the fetch URL. Page 1 works when fetch URL == that JSON URL; extra offsets need inner HTTP. Fine in production; in tests, fake those URLs or keep 404 fallback.
- Manifests still contain Laravel-era junk (`requires.xcrawlerii`, `queueSupervisor`, `defaultTargets`). Harmless; strip when touching manifests.
- `StreamCapable` is unused; Jable puts stream data in `meta['stream']`.
- `phpstan-baseline.neon` may still exist on disk; it is **not** included. Delete it when convenient.
- `coverage-merge.php` still concatenates file nodes (Codecov). Enforce is per-suite; leave merge alone unless Codecov looks wrong.
- Cookie handoff is in-memory only (process lifetime).

### P4 — Git (only if asked)

Workspace identity: author/committer `Viet Vu <jooservices@gmail.com>`, GitHub `soulevilx`, branches `master`/`develop` only. Conventional Commits. No AI trailers.

---

## 8. How to continue (commands)

```bash
cd /Users/vietvu/Sites/JOOservices/crawlerx
make build && make install
make lint
make test
make ci          # currently fails coverage:check
```

Capture one URL:

```bash
npm install
npx playwright install chromium
node tools/fixtures/capture.mjs \
  --url='https://en.jable.tv/new-release/' \
  --out=tests/Fixtures/jable/listing-new-release.html
```

JSON APIs (do not use Playwright):

```bash
curl -fsSL 'https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json' \
  -o tests/Fixtures/onepondo/list-newest-0.json
```

PHPUnit runs on host PHP directly when the host is `^8.5` (the `Makefile`
detects this and skips Docker); Docker is the fallback for a mismatched host,
and stays mandatory for the Playwright/FlareSolverr sidecars. Node/Playwright
can run on the host (Node v26 was available).

After test/fixture edits, re-run `make ci`. Coverage XML: `build/coverage/clover-Unit.xml`, `clover-Feature.xml`.

---

## 9. Pitfalls

- **`CrawlerXFactory` is a singleton.** Reset in `tearDown()` or tests leak fake chains / registries.
- **`site()` without type** used to skip URL detection. Fixed: even with `site()`, classifier still runs `detectUrl()` for type.
- **Manifest `listing` vs `performer_listing`.** MinnanoAv actress list is `performer_listing`. Wrong type → empty items.
- **OnePondo JSON through Playwright** becomes an HTML shell. Always curl JSON.
- **JavBus `/en/`** needs a listing URL rule (`#^/(?:en)?/?$#i`) — added. Without it: `AmbiguousUrlException`.
- **Do not** treat Turnstile script tags on a full page as a block.
- **Do not** put Unit tests back into the Feature suite to hit 85%.
- **Do not** recreate a companion fetch package.
- **Do not** add a second fixtures directory under `src/Adapters/*/fixtures/`.
  It existed once, was never read by `ManifestFixtureCatalog` (which only
  resolves `tests/Fixtures/`), drifted out of sync with the real captures,
  and shipped stale/fake data inside the published package. Removed
  2026-09-08 — keep fixtures in `tests/Fixtures/` only.
- **Site pagination "next page" logic must be derived from the listing's root
  path, not from the current request URL.** Jable's `pageFromUrl()` had this
  bug (see §7 P0b) and it was invisible in tests because the fake fixture
  added a `aria-label="Next Page"` link the real site doesn't have. When
  writing or reviewing a `pageFromUrl`-style helper, test it against a
  fixture for a page **other than page 1**, not just the canonical sample.

---

## 10. Owner quotes (intent)

- Package is crawlerx only. Pure PHP 8.5. No Laravel.
- Fetch, including Playwright / chrome stealth, belongs **here**.
- Fixtures from real sites via Playwright/curl. No fake HTML.
- Done only when the owner can use it as a crawler.
- Git not important for now.

When P0 + P1 + P2 are done, `make ci` is green, and a live `CrawlerX::url()` on jable (and ideally missav/javlibrary) returns a DTO, this package meets the definition of done.
