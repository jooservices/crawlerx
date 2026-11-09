# Architecture

This page maps the runtime path and the boundaries a contributor is expected to
extend. The code links below are the source of truth when behavior changes.

## Layers

The five named layers are the directories collected by [`deptrac.yaml`](../deptrac.yaml).
The `CrawlerXFactory`, `Services`, `Registry`, `Http`, `Enums`, and
`Exceptions` code also participates in the runtime, but is outside those five
Deptrac layers.

| Layer | Code | Responsibility | Allowed dependencies in Deptrac |
| --- | --- | --- | --- |
| Adapters | [`src/Adapters/`](../src/Adapters/) | Per-source URL routing and parsing; adapters declare their supported capabilities. | Contracts, Dto, Support |
| Fetch | [`src/Fetch/`](../src/Fetch/) | Fetch plans, method handlers, retries, sessions, budgets, guards, and the optional response cache. | Contracts, Dto, Support |
| Dto | [`src/Dto/`](../src/Dto/) | Request, profile, fetch, and parsed-result data passed between components. | None |
| Support | [`src/Support/`](../src/Support/) | Shared parsing and normalization utilities. | Contracts, Dto |
| Contracts | [`src/Contracts/`](../src/Contracts/) | Interfaces for adapters, adapter capabilities, clients, and fetch handlers. | Dto |

The rules describe intended dependency direction. In particular, Adapters must
not depend directly on Fetch; `deptrac.yaml` records the current `AbstractType`
to `ChallengeDetector` dependency as known debt. CI reports Deptrac output but
does not make that job a required gate; see
[`CONTRIBUTING.md`](../CONTRIBUTING.md) and [ADR-005](decisions/005-deptrac-report-only.md).

## Request lifecycle

`CrawlerX::url()` delegates to the factory and returns a clone-on-write request
builder. Calling `crawl()` classifies the URL, resolves the adapter manifest,
chooses a fetch plan, fetches the page, builds a `CrawlRequestDto`, dispatches
to the adapter capability, and returns a list or item result DTO.

```mermaid
sequenceDiagram
    actor Caller
    participant Facade as CrawlerX
    participant Factory as CrawlerXFactory
    participant Builder as CrawlRequestBuilder
    participant Orchestrator as CrawlOrchestrator
    participant Classifier as UrlClassifier and manifest registry
    participant Planner as FetchPlanResolver
    participant Chain as FetchFallbackChain
    participant Robots as RobotsGuard
    participant RobotsClient as ClientFactory HTTP client
    participant Runner as MethodAttemptRunner and guards
    participant Handler as Fetch method handler
    participant Registry as AdapterRegistry
    participant Executor as AdapterExecutor
    participant Adapter as Site adapter and parser type
    participant DTO as Result DTO

    Caller->>Facade: url(url)
    Facade->>Factory: create()
    Factory-->>Facade: CrawlOrchestrator
    Facade->>Orchestrator: url(url)
    Orchestrator-->>Caller: CrawlRequestBuilder
    Caller->>Builder: crawl()
    Builder->>Orchestrator: crawl(url, options)
    Orchestrator->>Classifier: classify URL and load source manifest
    Classifier-->>Orchestrator: source, crawl type, site profile
    Orchestrator->>Planner: resolve profile and fetch options
    Planner-->>Orchestrator: ordered method plan
    Orchestrator->>Chain: fetch(url, profile, plan)
    opt robots policy is enabled
        Chain->>Robots: check rules and read crawl delay
        opt robots.txt cache miss
            Robots->>RobotsClient: GET /robots.txt
            RobotsClient-->>Robots: body or unavailable
        end
        Robots-->>Chain: allowed or blocked; optional delay
    end
    loop each method in the plan
        Chain->>Runner: run method attempt
        opt handler supports response caching
            Runner->>Handler: check cached response
            Handler-->>Runner: cached result or cache miss
        end
        opt cache miss
            Runner->>Runner: apply host throttle
            opt first uncached attempt that passes throttle in this fetch
                Runner->>Runner: consult host circuit once
            end
            Runner->>Handler: fetch(url, profile, options)
            opt HTTP method
                Handler->>Handler: resolve configured proxy and make HTTP request
            end
            alt Playwright-family or Puppeteer method
                Handler->>Handler: browser script validates URL and uses SSRF egress proxy
            else HTTP, curl-impersonate, or FlareSolverr method
                Handler->>Handler: method transport has no browser-side SSRF guard
            end
            Handler-->>Runner: fetch result
        end
        Runner-->>Chain: result or retryable failure
    end
    Chain-->>Orchestrator: successful fetch result and metadata
    Orchestrator->>Orchestrator: build CrawlRequestDto with final URL and fetch result
    Orchestrator->>Registry: resolve source adapter
    Registry-->>Orchestrator: SiteAdapter
    Orchestrator->>Executor: dispatch by CrawlType capability
    Executor->>Adapter: parse CrawlRequestDto using fetched response
    Adapter-->>Executor: CrawlItemResultDto or CrawlListResultDto
    Executor-->>Orchestrator: result DTO
    Orchestrator-->>Caller: result DTO with fetch metadata
```

Key implementation points are [`CrawlerX.php`](../src/CrawlerX.php),
[`CrawlerXFactory.php`](../src/CrawlerXFactory.php),
[`CrawlRequestBuilder.php`](../src/Services/CrawlRequestBuilder.php),
[`CrawlOrchestrator.php`](../src/Services/CrawlOrchestrator.php),
[`FetchPlanResolver.php`](../src/Fetch/FetchPlanResolver.php),
[`FetchFallbackChain.php`](../src/Fetch/FetchFallbackChain.php),
[`MethodAttemptRunner.php`](../src/Fetch/Attempt/MethodAttemptRunner.php),
[`AdapterRegistry.php`](../src/Registry/AdapterRegistry.php), and
[`AdapterExecutor.php`](../src/Services/AdapterExecutor.php).

### Guard and transport behavior

- `RobotsGuard` runs before any fetch-method attempt. An explicit boolean
  `defaultCrawlConfig.respect_robots` in the source manifest overrides
  `CRAWLERX_RESPECT_ROBOTS` in either direction; when the manifest omits it,
  robots checking defaults to enabled. On a cache miss, the guard requests
  `/robots.txt` through the default `ClientFactory` HTTP client. This request
  happens before browser methods and is not protected by their SSRF scripts or
  egress proxy, so it may reach a private host even if a later browser method
  blocks the target. Unavailable `robots.txt` fails open, while a matching
  `Disallow` rule stops the crawl. The optional robots crawl delay is applied to
  host throttling.
- On each method attempt, a cache-aware handler is checked first. A cache hit
  skips the host throttle and circuit breaker. On a miss, `PreFetchGuard`
  applies the host throttle. It consults the circuit breaker at most once per
  fetch, after the first uncached attempt that passes throttling; later method
  attempts do not re-check it, even if another worker opens the circuit during
  that fetch.
- The HTTP page handler selects a proxy from request options, the site HTTP
  profile, then `CRAWLERX_HTTP_PROXY`. The robots request does not receive the
  request or site proxy; use `CRAWLERX_HTTP_PROXY` or the applicable libcurl
  proxy environment variable when it must also use a proxy. When CrawlerX
  leaves libcurl's proxy unset, libcurl may use `http_proxy` or `all_proxy`;
  `NO_PROXY` may bypass a configured proxy. An
  unset CrawlerX proxy therefore does not guarantee a direct connection. The
  optional response cache wraps successful HTTP responses only and is disabled
  by default.
- The browser-side SSRF guard applies to the Playwright-family methods
  (`Playwright`, `PlaywrightStealth`, and `ChromeStealth`) and `PuppeteerStealth`,
  whose scripts validate destinations and route browser traffic through the
  guarded egress proxy. The guard blocks unsafe hostnames and network addresses
  by default and pins approved DNS results. Private IP allowances require both
  `CRAWLERX_FETCH_LAB=1` and `CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS=1`. This
  protection is implemented in [`scripts/ssrf-guard.mjs`](../scripts/ssrf-guard.mjs),
  [`scripts/ssrf-proxy.mjs`](../scripts/ssrf-proxy.mjs),
  [`scripts/playwright-fetch.mjs`](../scripts/playwright-fetch.mjs), and
  [`scripts/puppeteer-stealth-fetch.mjs`](../scripts/puppeteer-stealth-fetch.mjs),
  called by [`PlaywrightFamilyFetchHandler`](../src/Fetch/Handlers/PlaywrightFamilyFetchHandler.php)
  and [`PuppeteerStealthFetchHandler`](../src/Fetch/Handlers/PuppeteerStealthFetchHandler.php).
  It does not cover the `BrowserLikely` profile as a whole:
  [`FlaresolverrFetchHandler`](../src/Fetch/Handlers/FlaresolverrFetchHandler.php)
  submits the target URL to an external service and needs separate destination
  or network controls. [`HttpFetchHandler`](../src/Fetch/Handlers/HttpFetchHandler.php),
  the robots request, and
  [`CurlImpersonateFetchHandler`](../src/Fetch/Handlers/CurlImpersonateFetchHandler.php)
  also do not use this browser-side guard and need separate controls.

These paths are implemented by [`FetchFallbackChain`](../src/Fetch/FetchFallbackChain.php),
[`RobotsGuard`](../src/Fetch/Guard/RobotsGuard.php),
[`PreFetchGuard`](../src/Fetch/Guard/PreFetchGuard.php),
[`HttpFetchHandler`](../src/Fetch/Handlers/HttpFetchHandler.php),
[`ClientFactory`](../src/Services/ClientFactory.php), and
[`ResponseCache`](../src/Fetch/Cache/ResponseCache.php).

## Extension points

### Add a source

Follow [`Adding a source`](adding-a-source.md). Put the adapter, source types,
and `manifest.json` under `src/Adapters/<Site>/`; implement `SiteAdapter` and
the capability contracts the manifest declares. Add fixture-backed tests and
register fixture samples in the manifest. Reuse the single-purpose concerns in
`src/Adapters/Concerns/` rather than copying shared parsing helpers.

### Add a fetch method

Implement [`FetchMethodHandler`](../src/Contracts/FetchMethodHandler.php),
including `supports()` and `fetch()`. Add the method to
[`FetchMethod`](../src/Enums/FetchMethod.php), then update the appropriate
default method arrays in `FetchMethod::httpOnlyChain()`,
`FetchMethod::browserChain()`, and/or `FetchMethod::defaultChain()`.
[`FetchProfile::chain()`](../src/Enums/FetchProfile.php) delegates to those
arrays. Also review any explicit `runtime.fetch.chain` values in source
manifests, which override the profile defaults. Register the handler in
[`CrawlerXFactory::defaultFetchChain()`](../src/CrawlerXFactory.php). Add
focused coverage under `tests/Unit/Fetch/` for support, result handling, and
fallback behavior.

### Add a guard

Put fetch-policy code under `src/Fetch/Guard/` with a focused unit test under
`tests/Unit/Fetch/Guard/`. Add a pre-handler check to
[`PreFetchGuard`](../src/Fetch/Guard/PreFetchGuard.php) when it must run for
each uncached method attempt; wire its dependency through
[`FetchFallbackChain`](../src/Fetch/FetchFallbackChain.php) and
[`CrawlerXFactory`](../src/CrawlerXFactory.php). Use the earlier robots check
in `FetchFallbackChain::fetch()` when the policy must reject the URL before
any method attempt begins. Cache hits currently bypass per-attempt guards, so
decide whether the new policy must also run before cache lookup.
