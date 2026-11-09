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
        Robots-->>Chain: allowed or blocked; optional delay
    end
    loop each method in the plan
        Chain->>Runner: run method attempt
        opt handler supports response caching
            Runner->>Handler: check cached response
            Handler-->>Runner: cached result or cache miss
        end
        opt cache miss
            Runner->>Runner: apply host throttle, then circuit breaker
            Runner->>Handler: fetch(url, profile, options)
            opt HTTP method
                Handler->>Handler: resolve configured proxy and make HTTP request
            end
            opt browser method
                Handler->>Handler: validate target through SSRF-protected egress proxy
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

- The robots guard runs before the fetch plan. A site manifest can override the
  runtime setting; when neither overrides it, robots checking is enabled.
  Unavailable `robots.txt` fails open, while a matching `Disallow` rule stops
  the crawl. The optional robots crawl delay is applied to host throttling.
- On each method attempt, a cache-aware handler is checked first. A cache hit
  skips the host throttle and circuit breaker. On a miss, `PreFetchGuard`
  applies the host throttle and then checks the circuit breaker.
- The HTTP handler can use an explicitly configured proxy. With no request,
  site, or process proxy configured, the HTTP client connects directly. The
  optional response cache wraps HTTP only and is disabled by default.
- Browser methods validate destinations through the browser-side SSRF guard
  and its egress proxy. That policy blocks unsafe hostnames and network
  addresses by default and pins approved DNS results. Private IP allowances
  require both `CRAWLERX_FETCH_LAB=1` and
  `CRAWLERX_BROWSER_ALLOW_PRIVATE_IPS=1`. This browser-specific protection is
  implemented in [`scripts/ssrf-guard.mjs`](../scripts/ssrf-guard.mjs) and
  [`scripts/ssrf-proxy.mjs`](../scripts/ssrf-proxy.mjs); it is separate from
  the configurable HTTP proxy.

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
[`FetchMethod`](../src/Enums/FetchMethod.php), choose which fetch profiles
include it in [`FetchProfile`](../src/Enums/FetchProfile.php), and register its
handler in [`CrawlerXFactory::defaultFetchChain()`](../src/CrawlerXFactory.php).
Add focused coverage under `tests/Unit/Fetch/` for support, result handling,
and fallback behavior.

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
