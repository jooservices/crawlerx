# Fetch strategy

CrawlerX chooses the least expensive fetch method that can return a usable page.
The strategy is configured by each adapter manifest under `runtime.fetch`.

## Profiles

| Profile | First method | Fallback behavior |
|---|---|---|
| `http_only` | HTTP | No browser fallback |
| `adaptive` | HTTP | Playwright, Puppeteer stealth, then FlareSolverr only after a detected challenge |
| `browser_likely` | Playwright | Puppeteer stealth, then FlareSolverr only after a detected challenge |

The legacy `playwrightFetchEnabled: true` setting maps to `browser_likely`. An
explicit `runtime.fetch.profile` takes precedence. The five browser-likely
adapters are Avfan, Jable, JavBus, Missav and JavLibrary.

## Readiness and terminal results

`runtime.fetch.readyMarkers` contains CSS selectors keyed by crawl page type.
A successful 2xx response without its marker is unusable and the next method
is tried. The markers are deliberately adapter-specific and come from the
adapter's existing parser selectors.

`runtime.fetch.soft404Markers` contains text or CSS-like markers for pages that
return HTTP 200 while reporting that the requested page does not exist.

The consumer receives these terminal error codes and retry hints:

| Condition | Code | Retryable |
|---|---|---|
| HTTP 404 | `not_found` | No |
| HTTP 410 | `gone` | No |
| HTTP 429, or 503 with `Retry-After` | `rate_limited` | Yes |
| Deadline or total budget exhausted | `timeout` | Yes |
| Challenge after all permitted methods | `challenge` | Yes |
| Other fetch failure | `network` | Yes |

Terminal results stop the fallback chain. FlareSolverr is never called unless
the preceding response was classified as a challenge.

## Budgets

The default total budget is 150 seconds. Individual method caps are 20 seconds
for HTTP, 45 seconds for browser methods and 60 seconds for FlareSolverr.
`FetchOptionsDto::deadlineSeconds` can set a shorter consumer deadline; no
method starts after that deadline.

## Local Fetch Lab

The Fetch Lab is a Docker-backed integration test against the repository's
fixture site. It exercises the real HTTP client, browser sidecar and a local
FlareSolverr-shaped fixture endpoint without contacting a live site.

```sh
make fetch-up
CRAWLERX_FETCH_LAB=1 vendor/bin/phpunit tests/Feature/FetchLab
make fetch-down
```

If the default host ports are already used by another local project, choose
unused host ports consistently for `fetch-up` and `fetch-down`, for example:

```sh
FLARESOLVERR_HOST_PORT=8193 FIXTURE_SITE_HOST_PORT=8082 make fetch-up
FLARESOLVERR_HOST_PORT=8193 FIXTURE_SITE_HOST_PORT=8082 make fetch-down
```

The lab is suitable for CI because all requests stay inside Docker and use
fixtures. Live adapter checks belong to the local canary tooling, not this
test suite.
