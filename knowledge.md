# CrawlerX — Knowledge Base

> Research-only document. No implementation yet.  
> Last updated: 2026-08-28 (CrawlOptionsDto, error handling, fetch overrides)

## Table of contents

1. [Goal](#1-goal-what-we-will-build)
2. [Sources studied](#2-sources-studied)
3. [Evolution](#3-evolution--three-generations)
4. [Sibling packages](#4-sibling-packages--what-we-must-align-with)
5. [Archive architecture](#5-archive-architecture--patterns-worth-keeping)
6. [Out of scope (YAGNI)](#6-what-the-archive-includes-but-crawlerx-v1-should-not-yagni)
7. [Brainstorm & decisions](#7-brainstorm--decisions)
8. [Parameter structure](#8-suggested-parameter-structure)
9. [Response structure](#9-suggested-response-structure)
10. [Internal class map](#10-internal-class-map-implementation-blueprint--not-built-yet)
11. [Design patterns](#11-design-patterns-checklist)
12. [Domain rules](#12-crawling-domain-rules-from-archives--for-adapter-authors)
13. [Developer experience (DX)](#13-developer-experience-dx)
14. [Site profile (one site)](#14-site-profile--structure--example)
15. [Open questions](#15-open-questions-unresolved)
16. [Cloudflare & browser fetch](#16-cloudflare--browser-fetch-confirmed-requirement)
17. [Multi-strategy fetch fallback](#17-multi-strategy-fetch--ordering--fallback-brainstorm--decision)
18. [References](#18-references-quick-links)

## 1. Goal (what we will build)

**Package:** `jooservices/crawlerx` — a Laravel service provider package.  
**Companion (fetch/bypass):** `jooservices/crawlerx-fetch` — optional; multi-method fetch chain.

**Public surface:**

```
Developer (happy path)
  → CrawlerX::url('https://…')->crawl()
  → package auto-detects site + type + fetch method
  → returns CrawlItemResultDto | CrawlListResultDto

Internal pipeline
  CrawlOrchestrator
    → UrlClassifier + SiteProfileRegistry
    → FetchFallbackChain (crawlerx-fetch: http → curl-impersonate → playwright → …)
    → CrawlHttpClient (jooservices/client v4 underneath)
  CrawlerXService (crawlerx core)
    → adapter Type fetches HTML via CrawlHttpClient
    → parse (site adapter + type strategy)
    → return DTO (jooservices/dto)
```

**DX principle:** caller provides **URL only** by default; site slug, crawl type, page, and fetch chain are **inferred** from URL + site profile. Optional `CrawlOptionsDto` on `->options()` for per-call HTTP and fetch overrides (§8.4–§8.5). Failures: `->crawl()` throws; `->tryCrawl()` returns `CrawlOutcomeDto` (§9.7, D29).

---

## 2. Sources studied

| Location | What it is |
| --- | --- |
| `/Users/vietvu/Sites/archives/JOOservices/XCrawler/` | XCrawler lineage: XCrawlerII app, Cursor greenfield attempts, multi-agent plans |
| `/Users/vietvu/Sites/archives/JOOservices.2/archive/src/XCrawlerII/backend/Modules/Crawler/` | **Most relevant** — extracted `CrawlerXService`, adapters, DTOs, 567+ files, fixture-driven tests |
| `/Users/vietvu/Sites/archives/JOOservices.2/XCrawlerII/` | Later monolith snapshot (Core + Catalog + Crawler wiring, Playwright, AI backoff) |
| `/Users/vietvu/Sites/JOOservices/client` | `jooservices/client` **v4.x** — PSR-18, RequestBuilder, Response wrapper, DTO mapping |
| `/Users/vietvu/Sites/JOOservices/dto` | `jooservices/dto` **v3.x** — immutable `Dto`, validation attributes, zero runtime deps |

**Current workspace:** `/Users/vietvu/Sites/JOOservices/crawlerx` is empty (greenfield package).

---

## 3. Evolution — three generations

### 3.1 XCrawlerII (legacy app module)

Path: `archives/JOOservices/XCrawler/XCrawlerII/Modules/JAV/`

- **Per-provider services:** `OnejavService`, `OneFourOneJavService`, `FfjavService`, `XcityIdolService`.
- **Per-page adapters:** `ItemAdapter`, `ItemsAdapter`, `TagsAdapter` operating on Symfony `DomCrawler` nodes.
- **Tight coupling:** config pagination state (`Config::get/set`), domain events (`ItemParsed`, `ProviderFetchStarted`), Laravel facades, persistence implied downstream.
- **HTTP:** bespoke `OnejavClient` wrappers (not shared client package).

**Lessons:** parsing logic is reusable; orchestration/events/config-state belong in the application, not the package.

### 3.2 XCrawlerII Crawler module (target architecture prototype)

Path: `archives/JOOservices.2/archive/src/XCrawlerII/backend/Modules/Crawler/`

Already implements the shape we want, as a Laravel **module** inside a monolith:

```php
// CrawlerXService — the exact entry point name we keep
final class CrawlerXService
{
    public function site(string $name): static;           // fluent, clone-based
    public function crawl(RequestDto $request): ListDto|ItemDto;
}
```

Supporting pieces:

| Piece | Role |
| --- | --- |
| `Registry` | Maps site slug → adapter class; resolves via container |
| `AdapterExecutor` | Dispatches by `CrawlType` to capability interfaces (ISP) |
| `AbstractBaseCrawler` | Site adapter: routes URL → Type class, owns HTTP client |
| `TypeInterface` + `Types/*` | Strategy per listing/detail/performer page shape |
| `ClientFactory` | Builds HTTP client from merged options (archive — port to `HttpClientFactory` + client v4) |
| `manifest.json` per adapter | Metadata: capabilities, base URL, throttle, fixtures |

**Registered site adapters (14):** onejav, onefouronejav, ffjav, xcity, warashi, onepondo, javbtc, javlibrary, javdatabase, javbus, jable, missav, minnanoav, avfan.

**Test discipline:** HTML fixtures under `tests/Fixtures/{site}/`; unit tests mock HTTP — no live network in CI.

### 3.3 XCrawlerIII plans (Cursor / Composer / Codex / Antigravity)

Path: `archives/JOOservices/XCrawler/xcrawler_plan_*.md`

- Greenfield **application** (not package) with Modules/Core + Modules/JAV.
- Documents field requirements, crawl edge cases, layering rules.
- Explicit non-goals: no migration from XCrawlerII.
- Reinforces: fixture-based parse tests, explicit sync over event chains, code normalization.

**Lessons for CrawlerX:** treat plans as **domain context** (movie/performer fields, normalization rules), not as package scope.

---

## 4. Sibling packages — what we must align with

### 4.1 `jooservices/dto` ^3.0

- All request/response objects should extend `JOOservices\Dto\Core\Dto`.
- Constructor-promoted `readonly` properties.
- `declare(strict_types=1)` everywhere.
- Optional: `#[Required]`, `#[Url]`, etc. on request DTO boundary.
- Prefer immutable results; consumers use `with()` if they need copies.

### 4.2 `jooservices/client` ^4.0

**Important drift:** archive Crawler module uses **pre-v4** API:

```php
// Archive (OLD — do not copy)
$client->get($url) → ResponseWrapperInterface → toPsrResponse()
// Interface: JOOservices\Client\Contracts\HttpClientInterface (verb methods)
```

**Current client v4:**

```php
// Current (USE THIS)
$client = ClientBuilder::create()->withBaseUri(...)->build();
$request = $client->requestBuilder()->get($path)->build();
$response = Response::from($client->sendRequest($request->toPsr()));
$html = $response->body();
```

- PSR-18 `ClientInterface` — HTTP 4xx/5xx returned, not thrown (opt-in `throw()`).
- Per-request `RequestOptions` DTO.
- Resilience middleware available but **optional** for CrawlerX v1.
- Testing: `ClientBuilder::fake()`, `TestResponseSequence`, `assertSent()`.

**CrawlerX rule:** adapter and integration tests use the same client fakes — CrawlerX does not introduce a parallel HTTP mock mechanism.

**Decision:** CrawlerX targets **client v4** only. Port adapter HTTP calls during extraction from archive.

### 4.3 PHP language standard (workspace)

- PHP `^8.5`, strict types, PSR-1/4/12, Pint `per` preset.
- SOLID / DRY / KISS / YAGNI are mandatory, not decorative.
- Type-hint PSR interfaces at boundaries (`Psr\Http\Client\ClientInterface` or wrapper around `JOOservices\Client\Client\HttpClient`).

---

## 5. Archive architecture — patterns worth keeping

### 5.1 Interface segregation (SOLID — I)

`SiteAdapter` only requires `name()`. Capabilities are separate:

| Interface | Method | Returns |
| --- | --- | --- |
| `ListingCapable` | `listing(RequestDto)` | `ListDto` |
| `DetailCapable` | `detail(RequestDto)` | `ItemDto` |
| `PerformerListingCapable` | `performerListing(RequestDto)` | `ListDto` |
| `PerformerDetailCapable` | `performerDetail(RequestDto)` | `ItemDto` |
| `UrlDetectCapable` | URL classification helpers | — |
| `StreamCapable` | non-HTML stream parse (Jable) | — |

`AdapterExecutor` uses `match ($request->type)` and fails fast if capability missing.

### 5.2 Strategy pattern — site × page type

```
OnejavCrawler (adapter)
  ├── listingRoutes() → TagActressListing | default Listing
  └── detailRoutes()  → default Detail
        └── each Type implements TypeInterface::execute(RequestDto)
```

Shared themes extracted (`BulmaTorrentListing`, `TagActressListing`) reduce duplication across onejav / 141jav / ffjav.

### 5.3 Registry pattern

- Register slug → adapter class.
- Resolve via PSR-11 container (constructor injection for manifest, client factory).
- Unknown slug → `AdapterNotFoundException`.

### 5.4 Fluent immutable site selection

`site()` clones the service; original instance has no site bound. Prevents accidental cross-site state (verified in `CrawlerXServiceTest`).

### 5.5 Fetch + parse separation

1. **Fetch:** `CrawlHttpClient` → `jooservices/client` v4 (M0 in core; M1–M6 in `crawlerx-fetch` configure the same client boundary).
2. **Parse:** Symfony DomCrawler + selectors + normalizers (`CodeNormalizer`, `SizeParser`, `QueryPageUrl`).
3. **Validate:** `AbstractType::assertUsableMovieDetail()` / `assertUsablePerformerDetail()` — fail if blocked/empty/challenge page.

### 5.6 Challenge / block detection

`AbstractType::isChallengeResponse()` checks Cloudflare headers and body markers (`Just a moment...`, `cf-browser-verification`). Reuse in package.

---

## 6. What the archive includes but CrawlerX v1 should NOT (YAGNI)

These live in the monolith **around** `CrawlerXService` — keep them in consumer apps:

| Concern | Archive class / feature | Why out of scope |
| --- | --- | --- |
| Queue jobs / Horizon | `CrawlTargetDispatcher`, crawl jobs | Application orchestration |
| Redis throttle gate | `CrawlRequestGate`, `CrawlRequestLimiter` | Infrastructure policy |
| AI backoff | `AiBackoffReviewService`, Ollama | Optional ops feature |
| Browser fetch orchestration | `PlaywrightHtmlFetch`, fetch chain | **`jooservices/crawlerx-fetch`** companion package |
| Mongo HTTP audit | `MongoDbLogger`, `crawlerx.audit` | Observability choice of host app |
| Catalog persistence | `CatalogMovieUpsertContract`, sync services | Domain layer |
| Import job dispatch / catalog sync | `ImportOrchestrator`, `ImportJobDispatcher` | Application workflow |
| URL auto-detection (classifier) | `ImportUrlClassifier`, `UrlDetectCapable` | **In scope for core** — required for URL-only DX (see §13) |
| Admin API / manifests UI | routes, `AdapterManifestDto` runtime admin | Host app |
| Laravel module (`nwidart`) | `module.json`, `Modules\Crawler\` namespace | Package uses `JOOservices\CrawlerX\` PSR-4 |

---

## 7. Brainstorm & decisions

### D1 — Package type

**Decision:** Standalone Composer + Laravel `ServiceProvider` package (`jooservices/crawlerx`), not an application module.

**Rationale:** Archive already proved `CrawlerXService` is useful outside a monolith; client + dto are standalone packages too.

### D2 — Namespace

**Decision:** `JOOservices\CrawlerX\` (matches `JOOservices\Client\`, `JOOservices\Dto\`).

### D3 — Keep the name `CrawlerXService`

**Decision:** Yes — established in archive, tests, and skill docs; clear intent.

### D4 — Public API layers (DX-first)

**Decision:** Three API levels; **Level 1 is the default** documented entry point.

| Level | API | When |
| --- | --- | --- |
| **L1 — URL only** | `CrawlerX::url($url)->crawl()` | 95% of consumer code |
| **L2 — URL + overrides** | `->page()`, `->type()`, `->site()`, `->options(CrawlOptionsDto)` | Ambiguous URLs, per-call fetch/HTTP overrides |
| **L3 — Full control** | `CrawlRequestDto` + `site()->crawl()` | Power users, batch jobs, internal wiring |

**Internal services (not primary DX):**

- `CrawlOrchestrator` — wires fetch chain + `CrawlerXService` (L1/L2 entry)
- `CrawlerXService::site($slug)->crawl($dto)` — direct parse entry when caller already built a `CrawlRequestDto`

No public `crawlDetail()` sugar methods — type is inferred or passed via builder.

**Public fluent surface (only these):** `url`, `site`, `type`, `page`, `options`, `crawl`. Everything operational goes in `CrawlOptionsDto` (see §8.4).

### D5 — DTO naming in package

**Decision:** Prefix or rename archive DTOs to avoid Laravel collisions:

| Archive | Package (proposed) |
| --- | --- |
| `RequestDto` | `CrawlRequestDto` |
| `ListDto` | `CrawlListResultDto` |
| `ItemDto` | `CrawlItemResultDto` |
| `PaginationDto` | `CrawlPaginationDto` |
| `CrawlType` | keep `CrawlType` enum |

### D6 — Result shape: core fields + meta bag

**Decision:** Keep archive pattern — fixed top-level fields + `meta` array for site-specific parsed data.

**Rationale:**

- 14 adapters put different keys in `meta` (performers, tags, blood_type, screenshots, etc.).
- Typed nested DTOs per site violates DRY and explodes class count.
- Consumer apps map `CrawlItemResultDto` → their domain DTOs.

**Future (not v1):** optional `MovieDetailMetaDto` / `PerformerDetailMetaDto` in a separate package or sub-namespace when a second consumer needs strict typing.

### D7 — HTTP client integration

**Decision:** Internal `HttpClientFactory` wrapping `ClientBuilder`; adapters depend on a narrow **`CrawlHttpClient` contract** (package-owned) that exposes `get(string $url): CrawlHttpResponse` (body + status + headers), implemented with client v4 underneath.

**Rationale:** Adapters should not know RequestBuilder fluency; all HTTP (production and tests) goes through `CrawlHttpClient` backed by `jooservices/client` v4. Tests use `ClientBuilder::fake()` — same boundary as production. Satisfies PSR-18 at the factory layer without leaking PSR-7 to every selector class.

### D8 — Adapter registration

**Decision v1:** Config file `config/crawlerx.php` with `'adapters' => ['onejav' => OnejavCrawler::class, ...]` registered in service provider.

**Defer:** Auto-discovery from `manifest.json` (archive pattern) until multiple plugins need drop-in registration.

### D9 — Symfony DomCrawler

**Decision:** Required dependency (`symfony/dom-crawler` + `symfony/css-selector`). Proven across all archive adapters.

### D10 — Laravel coupling level

**Decision:** `illuminate/support` + `illuminate/contracts` only for service provider, config merge, container. **No** Eloquent, Horizon, Redis, MongoDB in package requirements.

**Rationale:** Keeps package testable outside full Laravel stack if needed; matches client/dto portability.

### D11 — Testing strategy

**Decision:** One mock point — `jooservices/client` — everywhere:

- Fixture HTML per adapter under `tests/Fixtures/`.
- **Consumer apps and CrawlerX package tests** use `ClientBuilder::fake()`, `TestResponseSequence`, `assertSent()` — identical to client v4 docs.
- Tests call the **same L1 API** as production: `CrawlerX::url($url)->crawl()`.
- Zero live HTTP in CI.

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\CrawlerX\Facades\CrawlerX;

ClientBuilder::fake();
ClientBuilder::respond(
    'GET',
    'https://onejav.com/torrent/ymds282',
    (new TestResponseSequence())->push(
        TestResponse::make(200, [], file_get_contents(__DIR__.'/../Fixtures/onejav/detail-ymds282.html')),
    ),
);

$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();

ClientBuilder::clearFake();
```

### D12 — Port vs rewrite adapters

**Decision:** **Port** parse logic and selectors from archive module; **rewrite** HTTP layer for client v4 and namespaces.

**Priority adapters for first port (if/when implementing):** onejav, onefouronejav, ffjav, xcity (most documented in plans + fixtures).

### D22 — URL-only is the happy path

**Decision:** `CrawlerX::url(string $url)->crawl()` is the primary documented API. Package infers site (host), `CrawlType` (path rules), page (query string), and fetch chain (site profile).

**Archive basis:** `UrlDetectCapable`, `DetectsUrls` trait, `UrlDetectEngine`, `ImportUrlClassifier` (port simplified version into core as `UrlClassifier`).

### D23 — Per-call options via DTOs; browser driver config stays in profile

**Decision:** Public fluent API exposes only `url`, `site`, `type`, `page`, `options`, `crawl`. `->options()` accepts `CrawlOptionsDto` (not `array`). Sub-concerns are separate DTOs: `HttpOptionsDto`, `FetchOptionsDto`, `FetchChainDto` (see §8.4).

Per-call **fetch policy** (which handler, chain order, named profile) is overridable via `FetchOptionsDto`. Per-call **HTTP knobs** (`timeout`, `verify_ssl`, `headers`) via `HttpOptionsDto`.

Developers do **not** pass Playwright viewport, stealth scripts, FlareSolverr URL, or other browser-driver settings at call time — those remain in site profile + `crawlerx-fetch` config.

### D24 — Success DTOs only; no error fields on result DTOs

**Decision:** Public API never returns raw HTML. Success payloads are `CrawlItemResultDto` | `CrawlListResultDto` only — **no** `error` property on success DTOs. Optional `FetchMetaDto` on success when `crawlerx.expose_fetch_meta` is enabled.

### D25 — Package pair

**Decision:** `jooservices/crawlerx` (parse + URL detect + adapters) + `jooservices/crawlerx-fetch` (M1–M6 chain). L1 DX requires both installed for browser-protected sites; core alone works with M0 HTTP + `ClientBuilder::fake()` for adapter/parse tests.

### D26 — Tests mock `jooservices/client` only

**Decision:** Consumer apps and CrawlerX package tests both use `ClientBuilder::fake()` and call L1 `CrawlerX::url($url)->crawl()`. Browser fetch tests in `crawlerx-fetch` mock handlers at the fetch layer.

### D27 — `options` is a composed DTO

**Decision:** `CrawlRequestDto::options` and `CrawlRequestBuilder::options()` accept `?CrawlOptionsDto`, never a raw `array`. Each sub-concern is its own DTO for typed DX, validation, and `with()` immutability.

### D28 — Fetch override precedence

**Decision:** Per-call `FetchOptionsDto` overrides site profile fetch plan for **one request only**. Resolution order (highest wins):

1. `FetchOptionsDto.chain` — exact try order; ignores `method`, `profile`, and site default chain
2. `FetchOptionsDto.method` + `noFallback: true` — single handler only
3. `FetchOptionsDto.method` + `noFallback: false` — start at handler, then append remainder of base chain
4. `FetchOptionsDto.profile` — named preset from `config/crawlerx-fetch.php`
5. Site profile — `fetch_profile` + `fetch_chain` default

**Base chain** = result of step 4 or 5 before step 3 applies. If both `chain` and `method`/`profile` are set, `chain` wins (optionally validate and throw in strict mode).

### D29 — Hybrid error handling: throw default, outcome DTO for batch

**Decision:** Two public outcomes — same pipeline, different ergonomics:

| Method | On failure | Use when |
| --- | --- | --- |
| `->crawl()` | Throws domain exception | Default (~95%): single URL, Laravel jobs that should fail/retry |
| `->tryCrawl()` | Returns `CrawlOutcomeDto` with `ok: false` + `CrawlErrorDto` | Batch/import: listing → many details, partial failure OK |

**Rejected:** error fields on `CrawlItemResultDto` / `CrawlListResultDto` — forces every caller to check `$item->error` before using `$item->title`.

Fetch-layer failures retry internally (§17.4); only **exhausted chain** or **parse failure** surface to the caller. Transport errors (DNS, connection, TLS timeout) count as a failed fetch tier → fallback → `CrawlBlockedException` or `CrawlErrorCode::Blocked`.

---

## 8. Suggested parameter structure

### 8.1 `CrawlRequestDto`

Primary input to `CrawlerXService::crawl()`.

```php
final class CrawlRequestDto extends Dto
{
    public function __construct(
        public readonly string $url,
        public readonly CrawlType $type = CrawlType::Listing,
        public readonly int $page = 1,
        public readonly ?CrawlOptionsDto $options = null,
    ) {}
}
```

**Fields:**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `url` | `string` | yes | Absolute URL to crawl |
| `type` | `CrawlType` | yes (default Listing) | `listing`, `detail`, `performer_listing`, `performer_detail` |
| `page` | `int` | no (default 1) | Listing pagination hint; adapter may also parse from URL |
| `options` | `?CrawlOptionsDto` | no | Per-call HTTP + fetch overrides (§8.4) |

**Validation attributes (recommended on DTO):**

- `#[Url]` on `$url`
- `#[Min(1)]` on `$page`
- Custom rule: `$url` host must match selected adapter's allowed hosts (adapter-level, not DTO)

### 8.4 `CrawlOptionsDto` and sub-option DTOs

`CrawlOptionsDto` groups per-call overrides. **Not** a raw `array`.

```php
final class CrawlOptionsDto extends Dto
{
    public function __construct(
        public readonly ?HttpOptionsDto $http = null,
        public readonly ?FetchOptionsDto $fetch = null,
    ) {}
}

final class HttpOptionsDto extends Dto
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ?int $timeout = null,
        public readonly ?bool $verifySsl = null,
        public readonly array $headers = [],
    ) {}
}

final class FetchOptionsDto extends Dto
{
    public function __construct(
        public readonly ?FetchProfile $profile = null,
        public readonly ?FetchMethod $method = null,
        public readonly ?FetchChainDto $chain = null,
        public readonly bool $noFallback = false,
    ) {}
}

final class FetchChainDto extends Dto
{
    /**
     * @param list<FetchMethod> $methods  try order, left → right
     */
    public function __construct(
        public readonly array $methods,
    ) {}
}
```

**`FetchMethod` enum** (maps to §17 tiers): `Http`, `CurlImpersonate`, `Playwright`, `PlaywrightStealth`, `ChromeStealth`, `PuppeteerStealth`, `Flaresolverr`.

**`FetchProfile` enum** (named presets in `config/crawlerx-fetch.php`): `HttpOnly`, `BrowserLikely`, `Adaptive`.

**`HttpOptionsDto` merge:** per-call values override site profile `http` defaults (timeout, headers). Playwright viewport / `post_wait_ms` are **not** in `HttpOptionsDto` — site profile only.

### 8.5 Fetch override — how per-call options beat site default

Each site has a default fetch plan in its profile, e.g. `onejav` → `http_only` → `[http]`; `jable` → `browser_likely` → `[playwright, playwright_stealth, chrome_stealth, flaresolverr]`.

`FetchOptionsDto` replaces or trims that plan for **one crawl**. Adapter, selectors, and site HTTP/Playwright defaults are unchanged.

| You pass | Effect on site default |
| --- | --- |
| *(nothing)* | Use site profile chain as-is |
| `profile: BrowserLikely` | Replace whole chain with config preset |
| `method: Playwright`, `noFallback: false` | Start at Playwright, then rest of base chain after it |
| `method: ChromeStealth`, `noFallback: true` | Replace with chrome-stealth only |
| `chain: [Playwright, Flaresolverr]` | Replace with exact list; highest priority |

**Examples:**

```php
// onejav default [http] — swap to browser chain for this call
CrawlerX::url('https://onejav.com/torrent/abc')
    ->options(new CrawlOptionsDto(
        fetch: new FetchOptionsDto(profile: FetchProfile::BrowserLikely),
    ))
    ->crawl();

// jable default [playwright, …] — force http only for debug
CrawlerX::url('https://en.jable.tv/videos/abc/')
    ->options(new CrawlOptionsDto(
        fetch: new FetchOptionsDto(profile: FetchProfile::HttpOnly),
    ))
    ->crawl();

// jable — skip to chrome-stealth, keep tail of site chain
CrawlerX::url('https://en.jable.tv/videos/abc/')
    ->options(new CrawlOptionsDto(
        fetch: new FetchOptionsDto(method: FetchMethod::ChromeStealth),
    ))
    ->crawl();
// → [chrome_stealth, flaresolverr]

// exact order; wins over profile/method/site default
CrawlerX::url($url)
    ->options(new CrawlOptionsDto(
        fetch: new FetchOptionsDto(
            chain: new FetchChainDto([FetchMethod::Playwright, FetchMethod::Flaresolverr]),
        ),
    ))
    ->crawl();
```

See D28 for full precedence.

### 8.2 Site selection

**URL-first (L1):** site inferred from host — no `site()` needed.

**Explicit site (L2):** fluent `->site('onejav')` before `->url()` when overriding detection.

**Legacy (L3):** `$crawlerX->site('onejav')->crawl($request)` without URL builder.

**Rejected:** `site` inside `CrawlRequestDto` — duplicates URL host detection and fluent state.

### 8.3 Config-level defaults (`config/crawlerx.php`)

Mirror archive `crawlerx.php` subset:

```php
return [
    'http' => [
        'timeout' => 30,
        'verify_ssl' => true,
    ],
    'browser_headers' => [
        'default' => [ /* Accept, Accept-Language, ... */ ],
    ],
    'adapters' => [
        // 'onejav' => \JOOservices\CrawlerX\Adapters\Onejav\OnejavCrawler::class,
    ],
];
```

No audit/backoff/playwright sections in v1 config.

---

## 9. Suggested response structure

### 9.1 `CrawlType` → return type mapping

| Entry | On success | On failure |
| --- | --- | --- |
| `->crawl()` | `CrawlListResultDto` \| `CrawlItemResultDto` | throws (§13.8) |
| `->tryCrawl()` | `CrawlOutcomeDto` (`ok: true` + result DTO) | `CrawlOutcomeDto` (`ok: false` + `CrawlErrorDto`) |

| `CrawlType` | Success payload | `entity_type` |
| --- | --- | --- |
| `Listing` | `CrawlListResultDto` | `movie` |
| `Detail` | `CrawlItemResultDto` | `movie` |
| `PerformerListing` | `CrawlListResultDto` | `performer` |
| `PerformerDetail` | `CrawlItemResultDto` | `performer` |

Service signature:

```php
public function crawl(CrawlRequestDto $request): CrawlListResultDto|CrawlItemResultDto;

public function tryCrawl(CrawlRequestDto $request): CrawlOutcomeDto;
```

### 9.2 `CrawlItemResultDto`

```php
final class CrawlItemResultDto extends Dto
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $url,
        public readonly string $entityType,
        public readonly array $meta = [],
    ) {}
}
```

The item root has exactly `url`, `entity_type`, and `meta`. Movie identity and
data are serialized under `meta.movie`; performer identity and data are under
`meta.performer`. `meta` contains exactly one entity key matching
`entity_type`. Root `externalId` and `title` no longer exist.

| Field | Meaning |
| --- | --- |
| `url` | Canonical item URL after normalization |
| `entityType` / `entity_type` | `movie` or `performer` |
| `meta` | Exactly one typed serialized entity |

### 9.3 Typed entity DTOs

Adapters build these DTOs while parsing, then call `toItem($url)` to create the
public result. Listing cards and detail pages use the same entity types; listing
entities and performers nested in a movie may be partial.

**`MovieDto` (`meta.movie`):**

| Key | Type | Example |
| --- | --- | --- |
| `external_id` | `?string` | Site-native id |
| `title` | `?string` | Movie title |
| `code` | `?string` | `SSIS-001` (via `CodeNormalizer`) |
| `cover_url` | `?string` | |
| `description` | `?string` | |
| `date` | `?string` | ISO date `Y-m-d` |
| `duration` | `?int` | Minutes |
| `performers` | `list<PerformerDto>` | Partial nested performers are valid |
| `tags` | `list<string>` | |
| `screenshots` | `list<ScreenshotDto>` | URL and optional thumbnail |
| `metadata` | `array<string,mixed>` | Director, maker, label, series, and site extras |

**`PerformerDto` (`meta.performer`):**

| Key | Type |
| --- | --- |
| `external_id`, `name`, `name_japanese`, `url` | `?string` |
| `profile_image_url` | `?string` |
| `birth_date_raw` | `?string` |
| `height_raw` | `?string` |
| `size_raw` | `?string` |
| `aliases` | `list<string>` |
| `tags` | `list<string>` |
| `raw_profile`, `metadata` | `array<string,mixed>` |

CrawlerX never creates `track_id`; the app or queue that owns the process
assigns correlation identifiers.

### 9.4 `CrawlListResultDto`

```php
final class CrawlListResultDto extends Dto
{
    /**
     * @param list<CrawlItemResultDto> $items  Listing cards (partial detail)
     */
    public function __construct(
        public readonly string $url,
        public readonly int $page,
        public readonly string $entityType,
        public readonly array $items,
        public readonly CrawlPaginationDto $pagination,
    ) {}
}
```

Listings use `items[]`, with one `CrawlItemResultDto` per entity. Do not add a
list-level `meta.performers[]`. Every item has the same `entity_type` as its
containing list.

### 9.5 `CrawlPaginationDto`

```php
final class CrawlPaginationDto extends Dto
{
    public function __construct(
        public readonly ?int $currentPage,
        public readonly ?int $lastPage,
        public readonly ?int $nextPage,
        public readonly ?string $nextUrl,
        public readonly bool $hasNextPage,
    ) {}
}
```

Archive derives this from Bulma pagination DOM; JSON APIs (onepondo) may populate from response JSON instead.

### 9.6 `FetchMetaDto` (optional on result — logging / ops)

Attached when fetch ran through `crawlerx-fetch`; not required for consumer mapping.

```php
final class FetchMetaDto extends Dto
{
    /**
     * @param list<array{method: string, elapsed_ms: int, status: int, challenge: bool}> $attempts
     */
    public function __construct(
        public readonly string $methodUsed,
        public readonly int $elapsedMs,
        public readonly bool $challengeDetected,
        public readonly array $attempts = [],
    ) {}
}
```

### 9.7 `CrawlOutcomeDto` and `CrawlErrorDto` (`tryCrawl()` only)

Used by `->tryCrawl()` — never mixed into success result DTOs (D29).

```php
final class CrawlOutcomeDto extends Dto
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?CrawlItemResultDto $item = null,
        public readonly ?CrawlListResultDto $list = null,
        public readonly ?CrawlErrorDto $error = null,
    ) {}

    public function failed(): bool
    {
        return ! $this->ok;
    }
}

final class CrawlErrorDto extends Dto
{
    public function __construct(
        public readonly CrawlErrorCode $code,
        public readonly string $message,
        public readonly ?FetchMetaDto $fetch = null,
    ) {}
}
```

**`CrawlErrorCode` enum:**

| Code | Maps from exception | `fetch` on error |
| --- | --- | --- |
| `UnsupportedUrl` | `UnsupportedUrlException` | — |
| `AmbiguousUrl` | `AmbiguousUrlException` | — |
| `AdapterNotFound` | `AdapterNotFoundException` | — |
| `Blocked` | `CrawlBlockedException` | `FetchMetaDto` with `attempts` when available |
| `ParseFailed` | `CrawlParseException` | optional partial fetch meta |

**Batch example:**

```php
foreach ($list->items as $card) {
    $outcome = CrawlerX::url($card->url)->tryCrawl();

    if ($outcome->failed()) {
        Log::warning('detail failed', [
            'url' => $card->url,
            'code' => $outcome->error->code,
            'attempts' => $outcome->error->fetch?->attempts,
        ]);
        continue;
    }

    persist($outcome->item);
}
```

---

## 10. Internal class map (implementation blueprint — not built yet)

```
jooservices/crawlerx
JOOservices\CrawlerX\
├── CrawlerXServiceProvider
├── Facades\CrawlerX
├── Services\
│   ├── CrawlOrchestrator           # L1/L2 entry: detect → fetch → parse
│   ├── CrawlerXService             # parse facade (site + crawl / tryCrawl)
│   ├── CrawlRequestBuilder         # fluent: url(), page(), type(), options(), crawl(), tryCrawl()
│   ├── FetchPlanResolver           # site profile + FetchOptionsDto → try order (D28)
│   ├── AdapterExecutor
│   ├── UrlClassifier               # host + path → site slug + CrawlType
│   └── HttpClientFactory
├── Registry\
│   ├── AdapterRegistry             # slug → adapter class
│   └── SiteProfileRegistry         # slug → SiteProfileDto
├── Contracts\
│   ├── SiteAdapter, ListingCapable, DetailCapable, UrlDetectCapable, ...
│   ├── TypeInterface
│   └── CrawlHttpClient               # wraps jooservices/client v4; seedFromFetchResult() for orchestrator
├── Dto\
│   ├── CrawlRequestDto, CrawlOptionsDto
│   ├── HttpOptionsDto, FetchOptionsDto, FetchChainDto
│   ├── CrawlItemResultDto, CrawlListResultDto, CrawlPaginationDto
│   ├── CrawlOutcomeDto, CrawlErrorDto
│   ├── SiteProfileDto, HttpProfileDto, PlaywrightProfileDto, ThrottleProfileDto
│   ├── UrlDetectionResult
│   └── FetchMetaDto
├── Enums\
│   ├── CrawlType
│   ├── CrawlErrorCode
│   ├── FetchMethod
│   └── FetchProfile                # HttpOnly | BrowserLikely | Adaptive
├── Adapters\{Site}\...
├── Support\{CodeNormalizer, SizeParser, QueryPageUrl}
└── Exceptions\
    ├── AdapterNotFoundException
    ├── UnsupportedUrlException
    ├── AmbiguousUrlException
    ├── CrawlBlockedException
    └── CrawlParseException

jooservices/crawlerx-fetch (companion)
JOOservices\CrawlerX\Fetch\
├── CrawlerXFetchServiceProvider
├── FetchFallbackChain              # Chain of Responsibility M0–M6
├── Handlers\
│   ├── HttpFetchHandler
│   ├── CurlImpersonateFetchHandler
│   ├── PlaywrightFetchHandler
│   ├── PlaywrightStealthFetchHandler
│   ├── ChromeStealthFetchHandler
│   ├── PuppeteerStealthFetchHandler
│   └── FlareSolverrFetchHandler
├── Dto\FetchResultDto
├── Session\CookieHandoffStore      # M2→M1 hybrid (§17.5)
└── scripts/playwright-fetch.mjs    # port from archive
```

---

## 11. Design patterns checklist

| Pattern | Where |
| --- | --- |
| **Facade** | `CrawlerXService` hides registry + executor + adapters |
| **Registry** | `AdapterRegistry` |
| **Strategy** | `TypeInterface` implementations per page shape |
| **Adapter** | One class per external site (`OnejavCrawler`) |
| **Template method** | `AbstractHtmlType::fetchCrawler()` → subclass `execute()` |
| **Factory** | `HttpClientFactory`, `makeType()` in base crawler |
| **ISP** | Separate capability interfaces |
| **Dependency injection** | Adapters receive HTTP client + manifest/config via constructor |
| **Immutable fluent builder** | `site()` / `CrawlRequestBuilder` returns clone |
| **Chain of Responsibility** | `FetchFallbackChain` M0–M6 (`crawlerx-fetch`) |
| **Orchestrator** | `CrawlOrchestrator` coordinates detect + fetch + parse |

---

## 12. Crawling domain rules (from archives — for adapter authors)

### Code normalization

- Uppercase, strip spaces, insert hyphen: `MUDR360` → `MUDR-360`.
- FC2-PPV special cases; provider-specific formats (onejav vs 141jav).

### Pagination

- Query param `page` common (onejav theme).
- Some sites use path-based pages; adapter owns URL building via `QueryPageUrl`.

### Listing vs detail

- Listing returns lightweight `CrawlItemResultDto` entries (url + partial meta).
- Detail crawl enriches full meta; `assertUsableMovieDetail` guards quality.

### Edge cases to test with fixtures

- Empty body, HTTP 403/503, Cloudflare challenge pages.
- Partial HTML (missing cover but has title).
- Relative URLs → absolute via site normalizer.
- FC2 tag listings vs standard tag listings (onejav routing).

---

## 13. Developer experience (DX)

**Principle:** caller passes **URL only**; everything else is inferred unless explicitly overridden.

### 13.1 Level 1 — Just URL (default, ~95% of use)

```php
use JOOservices\CrawlerX\Facades\CrawlerX;

// Detail — type inferred from /torrent/ path
$item = CrawlerX::url('https://onejav.com/torrent/ymds282')->crawl();
echo $item->title;
echo $item->meta['code'];

// Listing — page inferred from ?page=2
$list = CrawlerX::url('https://onejav.com/new?page=2')->crawl();
foreach ($list->items as $card) {
    echo $card->url;
}
```

**Inferred automatically (developer does not pass):**

| Concern | Source |
| --- | --- |
| Site slug | URL host → `UrlClassifier` (`onejav.com` → `onejav`) |
| `CrawlType` | URL path rules per adapter (`/torrent/` → Detail, `/new` → Listing) |
| Page | Query `page` param |
| Fetch chain | `SiteProfileDto.fetchProfile` + chain (§17) |
| Headers, timeout, UA | Site profile defaults |
| Cloudflare bypass | `crawlerx-fetch` — invisible to caller |

### 13.2 Level 2 — URL + optional overrides

Fluent overrides for **crawl intent** (`page`, `type`, `site`) and **operational** settings (`CrawlOptionsDto`).

```php
$list = CrawlerX::url('https://onejav.com/new')
    ->page(3)
    ->type(CrawlType::Listing)
    ->crawl();

$item = CrawlerX::site('onejav')
    ->url('https://onejav.com/torrent/ymds282')
    ->crawl();

$item = CrawlerX::url('https://en.jable.tv/videos/abc/')
    ->options(new CrawlOptionsDto(
        http: new HttpOptionsDto(timeout: 90, headers: ['Cookie' => '...']),
        fetch: new FetchOptionsDto(method: FetchMethod::ChromeStealth, noFallback: true),
    ))
    ->crawl();
```

### 13.3 Level 3 — Power user / programmatic

```php
$crawlerX->site('onejav')->crawl(new CrawlRequestDto(
    url: 'https://onejav.com/new',
    type: CrawlType::Listing,
    page: 1,
    options: new CrawlOptionsDto(
        fetch: new FetchOptionsDto(profile: FetchProfile::BrowserLikely),
    ),
));
```

L3 is **not** for tests. Tests use L1 + `ClientBuilder::fake()` (see §13.12).

### 13.4 Fluent builder API

```php
CrawlerX::url(string $url): CrawlRequestBuilder
CrawlerX::site(string $slug): CrawlRequestBuilder

// Public fluent surface only:
->page(int $page): self
->type(CrawlType $type): self
->options(?CrawlOptionsDto $options): self
->crawl(): CrawlItemResultDto|CrawlListResultDto    // throws on failure
->tryCrawl(): CrawlOutcomeDto                        // soft-fail (D29)
```

### 13.5 Laravel integration

```bash
composer require jooservices/crawlerx jooservices/crawlerx-fetch
# php artisan vendor:publish --tag=crawlerx-config  (once, ops)
```

```php
final class SyncMovieAction
{
    public function run(string $url): void
    {
        $item = CrawlerX::url($url)->crawl();

        Movie::updateOrCreate(
            ['code' => $item->meta['code'] ?? $item->externalId],
            ['title' => $item->title, 'source_url' => $item->url],
        );
    }
}
```

No Playwright config, no fetch chain, no selectors at call site.

### 13.6 What developers never provide (by design)

| Concern | Owner |
| --- | --- |
| Fetch handler default | Site profile + `crawlerx-fetch` config |
| Fetch handler per-call override | `CrawlOptionsDto.fetch` (§8.5) |
| Playwright viewport, stealth scripts, FlareSolverr URL | Site profile + `crawlerx-fetch` config |
| Browser headers default, post-wait | Site profile |
| Throttle / backoff / job retry | Host app |
| CSS selectors | Adapter internals |
| `cf_clearance` cookie handoff | Fetch layer (§17.5) |

### 13.7 Error handling — two layers

| Layer | Behavior | Caller sees |
| --- | --- | --- |
| **Fetch** (`crawlerx-fetch`) | Retry next tier on failure (§17.4) | Nothing until chain exhausted |
| **Parse** (adapters) | Validate HTML; no retry | `CrawlParseException` or `CrawlErrorCode::ParseFailed` |

**Fetch failure signals** (try next tier): HTTP 403/503, CF challenge, empty body, timeout, network/transport error, Playwright `challenge: true`, FlareSolverr error.

**Parse failure:** challenge page slipped through, empty/unusable HTML, `assertUsableMovieDetail()` / `assertUsablePerformerDetail()` failed.

```php
// Default — throw
try {
    $item = CrawlerX::url($url)->crawl();
} catch (CrawlBlockedException $e) {
    // all fetch tiers failed — retry job, alert ops
} catch (CrawlParseException $e) {
    // HTML bad or site changed — may need adapter fix
}

// Batch — soft-fail (§9.7)
$outcome = CrawlerX::url($url)->tryCrawl();
```

### 13.8 Exceptions (`->crawl()` only)

| Exception | When |
| --- | --- |
| `UnsupportedUrlException` | Host not matched by any registered adapter |
| `AmbiguousUrlException` | Site matched but path rules unclear — use `->type()` |
| `AdapterNotFoundException` | Explicit `->site('unknown')` |
| `CrawlBlockedException` | All fetch methods exhausted (CF, 403, network, timeout, etc.) |
| `CrawlParseException` | HTML fetched but parser validation failed |

`->tryCrawl()` catches these internally and returns `CrawlOutcomeDto` with matching `CrawlErrorCode` (§9.7).

### 13.9 Mental model

```text
Developer:  URL (+ optional page / type / options)
     ↓
CrawlOrchestrator
     ├─ UrlClassifier        (site + type + page)
     ├─ SiteProfileRegistry  (fetch profile + chain floor)
     ├─ FetchFallbackChain   (M0…M6, invisible)
     ├─ CrawlHttpClient      (jooservices/client v4)
     └─ CrawlerXService      (parse → DTO)
     ↓
Developer:  CrawlItemResultDto | CrawlListResultDto  (or CrawlOutcomeDto via tryCrawl)
```

### 13.10 Listing → detail loop (common pattern)

```php
$list = CrawlerX::url('https://onejav.com/new')->crawl();

foreach ($list->items as $card) {
    $outcome = CrawlerX::url($card->url)->tryCrawl();
    if ($outcome->ok) {
        persist($outcome->item);
    }
}

if ($list->pagination->hasNextPage && $list->pagination->nextUrl !== null) {
    $next = CrawlerX::url($list->pagination->nextUrl)->crawl();
}
```

### 13.11 Archive usage (Level 3 — superseded for DX docs)

```php
$list = $crawlerX->site('onejav')->crawl(new CrawlRequestDto(
    url: 'https://onejav.com/new',
    type: CrawlType::Listing,
    page: 1,
));
```

Kept for internal wiring and programmatic callers; not the primary developer-facing example.

### 13.12 Testing — same API, mock `jooservices/client`

**Rule:** consumer apps **and** CrawlerX package tests use the same entry point and the same mock boundary.

| Who | Mock | Call |
| --- | --- | --- |
| Consumer app test | `ClientBuilder::fake()` | `CrawlerX::url($url)->crawl()` (L1) |
| CrawlerX adapter unit test | `ClientBuilder::fake()` | `CrawlerX::url($url)->crawl()` (L1) |
| `crawlerx-fetch` unit test | Mock `FetchMethodHandler` / chain | `FetchFallbackChain::fetch()` |

Adapter `Types/*` always obtain HTML via `CrawlHttpClient::get($url)` → `jooservices/client`. When `ClientBuilder::fake()` is active, fixture HTML is returned for matching URLs.

Challenge-page fixtures (e.g. `missav/cloudflare.html`) are registered the same way; parser assertions run against real `crawl()` output.

---

---

## 14. Site profile — structure & example

A **site profile** is all **metadata** for one source. **Adapter code** is how that source is parsed. Fetch reads profile; parse reads adapter.

### 14.1 Two layers

| Layer | Contains | Package |
| --- | --- | --- |
| **Site profile** | slug, base URL, capabilities, fetch profile/chain, HTTP/Playwright/throttle defaults, fixtures | `crawlerx` config or `manifest.json` |
| **Site adapter** | `{Site}Crawler`, `Selectors`, `Types/*` | `crawlerx` `Adapters/{Site}/` |

### 14.2 Profile document — `jable` (`browser_likely`)

Archive reference: `archives/.../Adapters/Jable/manifest.json` (`playwrightFetchEnabled: true`).

```json
{
  "slug": "jable",
  "display_name": "Jable",
  "base_url": "https://en.jable.tv",
  "adapter_class": "JOOservices\\CrawlerX\\Adapters\\Jable\\JableCrawler",
  "capabilities": ["listing", "detail", "performer_listing", "performer_detail"],

  "fetch_profile": "browser_likely",
  "fetch_chain": ["playwright", "playwright_stealth", "chrome_stealth", "flaresolverr"],
  "cookie_handoff_after_browser": true,

  "http": {
    "timeout": 30,
    "verify_ssl": true,
    "headers": {
      "User-Agent": "Mozilla/5.0 ... Chrome/131 ...",
      "Accept-Language": "en-US,en;q=0.9"
    }
  },

  "playwright": {
    "browser": "chromium",
    "headless": true,
    "post_wait_ms": 8000,
    "navigation_timeout_ms": 90000,
    "stealth_enabled": true,
    "viewport": { "width": 1440, "height": 900 }
  },

  "throttle": {
    "default_gap_seconds": 8,
    "min_gap_seconds": 3,
    "max_gap_seconds": 60
  },

  "pagination": { "strategy": "segment", "param": "page" },

  "url_detect": {
    "hosts": ["en.jable.tv", "jable.tv"],
    "rules": [
      { "pattern": "/videos/", "type": "detail" },
      { "pattern": "/new-release/", "type": "listing" },
      { "pattern": "/models/", "type": "performer_listing" }
    ]
  },

  "fixtures": [
    { "url": "https://en.jable.tv/new-release/", "file": "listing-new-release.html", "type": "listing" },
    { "url": "https://en.jable.tv/videos/fjin-091/", "file": "detail-fjin-091.html", "type": "detail" }
  ]
}
```

### 14.3 Profile DTO (PHP)

```php
final class SiteProfileDto extends Dto
{
    /**
     * @param list<CrawlType> $capabilities
     * @param list<string> $fetchChain
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $baseUrl,
        public readonly string $adapterClass,
        public readonly array $capabilities,
        public readonly FetchProfile $fetchProfile,
        public readonly array $fetchChain,
        public readonly HttpProfileDto $http,
        public readonly ?PlaywrightProfileDto $playwright,
        public readonly ThrottleProfileDto $throttle,
        public readonly UrlDetectProfileDto $urlDetect,
    ) {}
}
```

Registered via config:

```php
// config/crawlerx.php
'profiles' => [
    'jable' => /* SiteProfileDto or manifest path */,
    'onejav' => /* ... */,
],
```

Or loaded from per-adapter `manifest.json` (archive pattern — defer auto-discovery to v2 per D8).

### 14.4 Adapter folder — one site

```
src/Adapters/Jable/
├── JableCrawler.php       # SiteAdapter + capabilities; routes to Types
├── Selectors.php          # CSS constants
├── UrlNormalizer.php
├── manifest.json          # optional; mirrors profile
└── Types/
    ├── Listing.php
    ├── Detail.php
    ├── PerformerListing.php
    └── PerformerDetail.php

tests/Fixtures/jable/
├── listing-new-release.html
└── detail-fjin-091.html

tests/Unit/Adapters/Jable/
├── ListingTest.php
└── DetailTest.php
```

Archive `JableCrawler` is thin — delegates to `Types/Listing`, `Types/Detail`; **no fetch logic inside adapter**.

### 14.5 Contrast — `onejav` vs `jable`

| Field | `onejav` | `jable` |
| --- | --- | --- |
| `fetch_profile` | `http_only` | `browser_likely` |
| `fetch_chain` | `['http']` | `['playwright', 'playwright_stealth', …]` |
| `playwright` block | `null` | required |
| `capabilities` | listing, detail | + performer listing/detail |
| Developer call | `CrawlerX::url('https://onejav.com/…')` | same — **identical DX** |

Adding a site = one profile entry + one `Adapters/{Site}/` folder.

### 14.6 Runtime flow (one request)

```text
CrawlerX::url('https://en.jable.tv/videos/fjin-091/')->crawl()
  │
  ├─ UrlClassifier
  │    host → slug 'jable'
  │    path → CrawlType::Detail
  │
  ├─ SiteProfileRegistry::get('jable')
  │    fetch_profile: browser_likely → chain starts at M2
  │
  ├─ FetchPlanResolver (site profile + options.fetch → try order)
  │
  ├─ FetchFallbackChain::fetch(url, profile, plan)
  │    playwright → (fail?) → playwright_stealth → …
  │    → FetchResultDto { html, status, finalUrl, methodUsed }
  │
  ├─ CrawlHttpClient configured for this request (fetch result → client responses)
  │
  └─ CrawlerXService::site('jable')->crawl(CrawlRequestDto)
       └─ JableCrawler::detail() → Types/Detail → CrawlHttpClient::get() → parse → CrawlItemResultDto
```

### 14.7 Orchestrator sketch (internal)

```php
final class CrawlOrchestrator
{
    public function crawl(CrawlRequestBuilder $builder): CrawlItemResultDto|CrawlListResultDto
    {
        $url = $builder->url();
        $detection = $this->urlClassifier->classify($url);

        $profile = $this->profiles->get($detection->siteSlug);
        $type = $builder->type() ?? $detection->crawlType;
        $page = $builder->page() ?? $detection->page;

        $fetchPlan = $this->fetchPlanResolver->resolve(
            siteProfile: $profile,
            fetchOptions: $builder->options()?->fetch,
        );

        $fetch = $this->fetchChain->fetch($url, $profile, $fetchPlan);

        // Browser/M1–M6 tiers do not bypass CrawlHttpClient — orchestrator seeds client responses
        $this->crawlHttpClient->seedFromFetchResult($fetch);

        $request = new CrawlRequestDto(
            url: $fetch->finalUrl ?? $url,
            type: $type,
            page: $page,
            options: $builder->options(),
        );

        $result = $this->crawlerX->site($profile->slug)->crawl($request);
        // attach FetchMetaDto if needed
        return $result;
    }
}
```

---

## 15. Open questions (unresolved)

| # | Question | Lean |
| --- | --- | --- |
| Q1 | Ship adapters inside `crawlerx` or separate `crawlerx-jav` plugin package? | Start with core + 1–2 adapters in main repo; split when adapter count hurts CI time |
| Q2 | `manifest.json` per adapter in v1? | Defer; PHP config array enough initially |
| Q3 | Expose `AdapterExecutor` publicly for advanced callers? | No; keep package entry at `CrawlerXService` |
| Q4 | Support JSON API adapters (onepondo) in same `AbstractHtmlType` tree? | Yes — separate `AbstractJsonType` sibling, same `TypeInterface` |
| Q5 | Minimum Laravel version? | Align with workspace Laravel packages (likely `^11\|^12` + PHP 8.5) — verify when scaffolding |
| Q6 | Playwright in `crawlerx` core or separate package? | Separate `crawlerx-fetch` companion; core uses `CrawlHttpClient` + client v4 only |
| Q7 | Upgrade stealth beyond minimal init script? | Yes — ordered fallback chain (see §17); not single-method |
| Q8 | Puppeteer AND Playwright both? | Avoid by default; Puppeteer tier optional M5 (§17) |
| Q9 | `FetchMetaDto` on every result or opt-in? | Opt-in via config `crawlerx.expose_fetch_meta` default false |
| Q10 | Manifest.json vs PHP config for profiles? | PHP config v1; manifest per adapter v2 (D8) |

---

## 16. Cloudflare & browser fetch (confirmed requirement)

### 16.1 Yes — some sites need more than plain HTTP

Archive evidence is explicit. Plain `jooservices/client` (cURL) works for torrent-listing sites; **streaming / catalog sites behind Cloudflare need headless browser fetch**.

**Per-site `playwrightFetchEnabled` in adapter manifests:**

| Fetch method | Sites |
| --- | --- |
| **HTTP only** (`false`) | onejav, onefouronejav, ffjav, xcity, warashi, onepondo, minnanoav, javbtc, javdatabase, javbus |
| **Playwright required** (`true`) | **jable**, **missav**, **javlibrary**, **avfan** |

Ops doc states the reason directly: *"plain HTTP is blocked (for example Cloudflare on Jable)"*.

### 16.2 What the archive uses (baseline; extended by §17)

XCrawlerII archive baseline does **not** use puppeteer-extra, FlareSolverr, or curl-impersonate yet. **CrawlerX ecosystem adds M1–M6 per §17.** Archive uses:

1. **Playwright** (`playwright` npm ^1.61, Chromium)
2. **Node sidecar script:** `backend/scripts/crawl/playwright-fetch.mjs`
3. **Minimal custom stealth** — a small `addInitScript`, not a full stealth library:

```javascript
// playwright-fetch.mjs (archive)
Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });
window.chrome = { runtime: {}, loadTimes: function(){}, csi: function(){} };
```

Configurable per site: `stealth_enabled` (default `true`), realistic UA, viewport, locale, timezone, `storage_state_path` (cookies), `post_wait_ms` (default 8000), scroll + networkidle wait.

4. **Challenge detection** after fetch — marks `challenge: true` / status 403 when HTML contains:
   - `Just a moment...` (title or body)
   - `challenges.cloudflare.com`
   - `cf-browser-verification`

PHP parser layer (`AbstractType::isChallengeResponse()`) also rejects CF challenge pages if they reach the adapter.

### 16.3 Architecture — fetch and parse are separate (important)

```text
CrawlOrchestrator
  → FetchFallbackChain (M0–M6 per site profile)
  → FetchResultDto
  → CrawlHttpClient seeded with fetch result (jooservices/client underneath)
  → CrawlerXService::crawl()
       → adapter Type::execute()
            → CrawlHttpClient::get($url)  → HTML
            → DomCrawler parse → DTO
```

**Adapters never import Playwright or browser drivers.** They only call `CrawlHttpClient`. The fetch package owns M1–M6; the orchestrator bridges fetch output into the same HTTP client boundary adapters already use.

### 16.4 What happens when bypass still fails

- Playwright script returns JSON with `challenge: true` and challenge HTML.
- `MissavAdapterTest::test_listing_fails_on_cloudflare_challenge` uses fixture `missav/cloudflare.html` — parser throws *"blocked or unavailable"*.
- Ops doc: switching fetch back to `http` *"falls back to Guzzle; CF-protected pages may fail"*.

**Reality check:** minimal stealth + headless Chromium is **not guaranteed** against modern Cloudflare (Turnstile, managed challenge). Archive mitigates with wait time, UA rotation, optional `storage_state_path`, debug video/HTML artifacts — but not a 100% bypass.

### 16.5 Multi-method bypass (see §17 for full chain)

| Approach | Tier | In archive? |
| --- | --- | --- |
| curl-impersonate | M1 | No — add in crawlerx-fetch |
| Playwright + minimal init stealth | M2 | Yes (primary) |
| playwright-extra / rebrowser-playwright | M3 | No |
| Headed chrome-stealth | M4 | No |
| puppeteer-extra-plugin-stealth | M5 | No |
| FlareSolverr | M6 | No |

### 16.6 Package split

| Layer | Package | Responsibility |
| --- | --- | --- |
| Core | `jooservices/crawlerx` | Parse + URL detect + adapters; M0 via `CrawlHttpClient` |
| Fetch | `jooservices/crawlerx-fetch` | M1–M6 chain, cookie handoff, Node scripts |

Do **not** block core on Node/Playwright — `composer suggest` for fetch package.

### 16.7 Decisions (browser fetch)

**D13 — Cloudflare sites are in scope for the ecosystem, not necessarily core v1**

- Core `crawlerx` owns parse + `CrawlHttpClient` (client v4).
- Browser fetch is a **companion** concern in `crawlerx-fetch`; orchestrator seeds client responses after fetch.

**D14 — Default fetch strategy per adapter**

- Mirror manifest flags: HTTP for onejav/141jav/ffjav/xcity; Playwright for jable/missav/javlibrary/avfan.
- Expose as adapter metadata or config, resolved by host app before calling `crawl()`.

**D15 — Stealth implementation** → superseded by **§17 multi-strategy fallback chain**

**D16 — CI / testing**

- Unit tests: `ClientBuilder::fake()` + fixture HTML — including `cloudflare.html` challenge fixtures.
- Playwright smoke: optional integration job (`XC_PLAYWRIGHT_VERIFY_LIVE=1`), not required for adapter parse CI.

### 16.8 Fetch meta on results (not request options)

When fetch runs through `crawlerx-fetch`, attach optional `FetchMetaDto` on the crawl result (opt-in via `crawlerx.expose_fetch_meta`):

```php
// On CrawlItemResultDto / CrawlListResultDto when enabled
$fetchMeta = new FetchMetaDto(
    methodUsed: 'playwright',
    elapsedMs: 8500,
    challengeDetected: false,
    attempts: [/* … */],
);
```

Ops/audit layers read `FetchMetaDto`; it is not passed through `CrawlRequestDto::options`.

---

## 17. Multi-strategy fetch — ordering & fallback (brainstorm + decision)

User requirement: support **multiple bypass methods** with a defined **try order** and **fallback** when one fails.

Methods under consideration:

| ID | Method | What it fixes |
| --- | --- | --- |
| M0 | **HTTP** (`jooservices/client` / plain cURL) | Baseline; no bypass |
| M1 | **curl-impersonate** | TLS / JA3 / HTTP/2 fingerprint (browser-like without JS) |
| M2 | **Playwright** (minimal init stealth — archive) | JS challenge, cookies, DOM after load |
| M3 | **Playwright + enhanced stealth** (`rebrowser-playwright` or `playwright-extra` stealth) | CDP leaks, stronger headless evasion |
| M4 | **chrome-stealth** (headed / patched Chromium) | Same as M3 but non-headless or undetected-chromedriver-style |
| M5 | **puppeteer-extra-plugin-stealth** | Puppeteer-stack stealth (separate from Playwright) |
| M6 | **FlareSolverr** | External solver service (browser inside container) |

### 17.1 What each method cannot do

| Method | TLS fingerprint | JS / Turnstile | Cost | Speed | Ops deps |
| --- | --- | --- | --- | --- | --- |
| M0 HTTP | No | No | Lowest | Fastest | None |
| M1 curl-impersonate | **Yes** | No | Low | Fast | `curl-impersonate` binary in PATH |
| M2 Playwright minimal | Yes (real Chromium) | **Partial** | Medium | Slow (~8s+) | Node + Playwright + Chromium |
| M3 Enhanced PW stealth | Yes | **Better** | Medium | Slow | Node + patched PW build |
| M4 Headed chrome-stealth | Yes | **Better** | High | Slowest | Display or Xvfb; harder in Docker |
| M5 puppeteer-extra-stealth | Yes | **Better** | Medium | Slow | Node + Puppeteer (2nd browser stack) |
| M6 FlareSolverr | Yes | **Often** | High | Slowest | Sidecar container + network |

**Key insight (2026 anti-bot practice):** Cloudflare is layered. Fixing TLS alone is not enough when the response body is a Managed Challenge page — you need a browser tier. Conversely, jumping straight to Playwright on every 403 wastes RAM/time when **only TLS** was wrong.

### 17.2 Recommended fallback order (default chain)

**Principle:** cheapest + fastest + fewest deps first; escalate only on **failure signals** (see §17.4).

```text
M0 http
  ↓ fail
M1 curl_impersonate
  ↓ fail
M2 playwright          (minimal stealth — archive script)
  ↓ fail
M3 playwright_stealth  (rebrowser-playwright OR playwright-extra-stealth)
  ↓ fail
M4 chrome_stealth      (headed Chromium / undetected-style — same PW driver, headless=false)
  ↓ fail
M5 puppeteer_stealth   (puppeteer-extra-plugin-stealth — OPTIONAL tier)
  ↓ fail
M6 flaresolverr
  ↓ fail
→ CrawlBlockedException (all methods exhausted)
```

**Why this order (not PW first):**

1. **M0 before M1** — zero-cost; most torrent sites (onejav, 141jav, ffjav, xcity) never leave M0.
2. **M1 before M2** — curl-impersonate is ~10–100× faster than launching Chromium; industry pattern is TLS-first when plain curl 403s but HTML is not yet a challenge page.
3. **M2 before M3** — archive-proven; minimal deps; good enough for jable/missav/javlibrary/avfan in many cases.
4. **M3 before M4** — enhanced stealth in headless mode before headed (Docker/CI friendly).
5. **M4 before M5** — stay on Playwright family; avoid second browser stack until necessary.
6. **M5 optional** — `puppeteer-extra-plugin-stealth` maintenance has lagged; only enable if M2–M4 fail consistently. **Prefer M3 (`rebrowser-playwright`) over M5** when hardening Playwright.
7. **M6 last** — external service, latency, single point of failure; best as safety net not primary.

**Where “chrome-stealth” sits:** treat as **M4** — same Playwright/Puppeteer driver with `headless: false`, optional Xvfb in Linux, or `rebrowser-playwright` patches. Not a separate npm package name in our stack unless we standardize on one.

**Where puppeteer-extra sits:** **M5**, after Playwright tiers fail — avoids maintaining Puppeteer for 90% of sites.

**Where FlareSolverr sits:** **M6**, last resort.

### 17.3 Site profiles — skip lower tiers when known

Manifest / adapter metadata should set **`fetch_floor`** (minimum tier), not just a single method:

| Profile | `fetch_floor` | Default chain starts at | Sites (archive) |
| --- | --- | --- | --- |
| `http_only` | M0 | M0 → (no escalation unless `allow_escalation=true`) | onejav, 141jav, ffjav, xcity, … |
| `browser_likely` | M2 | M2 → M3 → M4 → M5 → M6 (skip M0–M1 on production crawl) | jable, missav, javlibrary, avfan |
| `adaptive` | M0 | Full chain M0→…→M6 | Unknown URLs, import classifier |

**Rationale for `browser_likely`:** archive already marks these `playwrightFetchEnabled: true`. Trying M0/M1 first on jable in production adds 1–2 doomed attempts per URL. Still useful in **`adaptive`** mode for new sites.

Config example:

```php
// config/crawlerx-fetch.php
'chains' => [
    'default' => ['http', 'curl_impersonate', 'playwright', 'playwright_stealth', 'chrome_stealth', 'puppeteer_stealth', 'flaresolverr'],
    'http_only' => ['http'],
    'browser_likely' => ['playwright', 'playwright_stealth', 'chrome_stealth', 'puppeteer_stealth', 'flaresolverr'],
],
'site_profiles' => [
    'onejav' => 'http_only',
    'jable' => 'browser_likely',
    'missav' => 'browser_likely',
],
```

### 17.4 Failure signals — when to fall back

A fetch attempt **fails** (try next method) when any of:

| Signal | Detection |
| --- | --- |
| HTTP 403 / 503 | Status code |
| CF challenge body | `Just a moment`, `challenges.cloudflare.com`, `cf-browser-verification` |
| CF header | `cf-mitigated: challenge` |
| Empty / truncated body | `< 512 bytes` or no `<html` |
| Playwright JSON | `challenge: true` from sidecar script |
| FlareSolverr | `status: "error"` or no `solution.response` |
| Timeout | Per-method timeout exceeded |
| Network / transport | DNS failure, connection refused, TLS error — treated as tier failure |

A fetch **succeeds** (stop chain, pass HTML to parser) when:

- Status 2xx (configurable: accept 404 for detail probe)
- Body is non-empty HTML
- **Not** a challenge page by heuristics above
- Optional: adapter-level “soft success” (e.g. 404 page with valid “not found” template) — site-specific

Log each attempt: `{ method, elapsed_ms, status, challenge, bytes }` for ops tuning.

### 17.5 Hybrid mode (optimization, not fallback)

Separate from fallback: **session cookie handoff** after a browser tier succeeds:

```text
M2/M3/M4 solves challenge once
  → extract cf_clearance + session cookies
  → subsequent listing pages use M1 curl_impersonate + cookie jar (same sticky IP)
  → on cookie expiry or 403, re-enter fallback chain at M2
```

This is how high-volume scrapers minimize browser launches (2026 best practice). Implement in **`crawlerx-fetch`**, not in parse core.

Requirements for cookie handoff:

- **Sticky egress IP** per session (rotating IP invalidates `cf_clearance`)
- Cookie store keyed by `{site_slug, egress_ip}` with TTL aligned to CF cookie expiry
- UA / TLS profile must match between M1 and M2 (same Chrome version label)

### 17.6 Package split (updated)

| Package | Methods | Notes |
| --- | --- | --- |
| `jooservices/crawlerx` | M0 via `CrawlHttpClient` | Core parse; client v4 underneath |
| `jooservices/crawlerx-fetch` | M1–M6 orchestration | `FetchFallbackChain`, `FetchResultDto`, seeds `CrawlHttpClient` |
| Suggest deps | M2–M5 Node scripts | Optional composer `suggest`; CI does not require them |
| Suggest deps | M6 | `flaresolverr` URL in config; Docker compose example |

**Chain of Responsibility** pattern: each `FetchMethodHandler` implements `supports()` + `fetch()`; `FetchFallbackChain` iterates until success or exhaustion.

**Do not** embed M1–M6 inside individual adapters — keep fetch orchestration one level above `CrawlerXService::crawl()`.

### 17.7 Implementation priority (when we build)

| Phase | Deliver |
| --- | --- |
| P0 | M0 HTTP via `CrawlHttpClient` + `ClientBuilder::fake()` test harness |
| P1 | M2 Playwright minimal (port archive script) + failure detection |
| P2 | M1 curl-impersonate handler + insert into chain before M2 |
| P3 | M3 enhanced Playwright stealth (`rebrowser-playwright` evaluate first) |
| P4 | M6 FlareSolverr handler (config URL, health check) |
| P5 | M4 headed / chrome-stealth mode (ops-heavy) |
| P6 | M5 puppeteer-extra (only if M3 insufficient in production metrics) |
| P7 | Cookie handoff M2→M1 hybrid session cache |

### 17.8 Decisions (multi-fetch)

**D17 — Default fallback order**

`http → curl_impersonate → playwright → playwright_stealth → chrome_stealth → puppeteer_stealth → flaresolverr`

**D18 — Puppeteer is optional tier M5, not parallel primary**

Do not run Puppeteer and Playwright as co-primary stacks. Prefer `rebrowser-playwright` / `playwright-extra` before adding Puppeteer.

**D19 — Site `fetch_floor` profiles**

`http_only` | `browser_likely` | `adaptive` — controls where chain starts.

**D20 — FlareSolverr is last automated tier**

External dependency; never first choice for cost and reliability.

**D21 — Fallback is fetch-package concern**

`CrawlerXService::crawl()` does not iterate M1–M6. Adapters call `CrawlHttpClient`; `crawlerx-fetch` + `CrawlOrchestrator` own fetch fallback and seed client responses before parse (SRP).

---

## 18. References (quick links)

- Archive `CrawlerXService`: `archives/JOOservices.2/archive/src/XCrawlerII/backend/Modules/Crawler/app/Services/CrawlerXService.php`
- Archive DTOs: `.../Modules/Crawler/app/Dto/`
- Archive onejav detail parser: `.../Adapters/Onejav/Types/Detail.php`
- Archive skill: `.../backend/ai/skills/crawler/SKILL.md`
- XCrawlerIII plan: `archives/JOOservices/XCrawler/xcrawler_plan_composer.md`
- Client README: `/Users/vietvu/Sites/JOOservices/client/README.md`
- DTO README: `/Users/vietvu/Sites/JOOservices/dto/README.md`
- PHP standard: `/Users/vietvu/Sites/JOOservices/docs/02-engineering/02-quality/php-language-standard.md`
- Playwright crawl fetch ops: `archives/JOOservices.2/archive/src/XCrawlerII/docs/03-operations/02-playwright-crawl-fetch.md`
- Playwright script: `archives/JOOservices.2/archive/src/XCrawlerII/backend/scripts/crawl/playwright-fetch.mjs`
- CF challenge fixture: `.../Modules/Crawler/tests/Fixtures/missav/cloudflare.html`
- curl-impersonate: https://github.com/lwthiker/curl-impersonate
- FlareSolverr: https://github.com/FlareSolverr/FlareSolverr
- rebrowser-playwright (CDP leak patches): https://github.com/rebrowser/rebrowser-playwright
- Archive URL detection: `.../Modules/Crawler/app/Adapters/Concerns/DetectsUrls.php`
- Archive URL classifier: `.../Modules/Crawler/app/Services/Import/ImportUrlClassifier.php`
- Archive Jable manifest: `.../Modules/Crawler/app/Adapters/Jable/manifest.json`
