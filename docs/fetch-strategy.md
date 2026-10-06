# Fetch strategy

CrawlerX chooses the least expensive fetch method that can return a usable page.
The strategy is configured by each adapter manifest under `runtime.fetch`.

## Profiles

| Profile | First method | Fallback behavior |
|---|---|---|
| `http_only` | HTTP | No browser fallback |
| `adaptive` | HTTP | Optional curl-impersonate, then Playwright, then FlareSolverr only after a detected challenge |
| `browser_likely` | Playwright | FlareSolverr only after a detected challenge |

The legacy `playwrightFetchEnabled: true` setting maps to `browser_likely`. An
explicit `runtime.fetch.profile` takes precedence. The five browser-likely
adapters are Avfan, Jable, JavBus, Missav and JavLibrary.

## Readiness and terminal results

`runtime.fetch.readyMarkers` contains CSS selectors keyed by crawl page type.
A successful 2xx response without its marker is unusable and the next method
is tried. The markers are deliberately adapter-specific and come from the
adapter's existing parser selectors.

`runtime.fetch.soft404Markers` contains text or CSS-like markers for pages that
report that the requested page does not exist. Text markers are matched against
the title and visible document text; script, style and template contents are
ignored.

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

## Sessions and login cookies

`SessionStore` keeps solved cookies, the matching user agent, the solving
method, browser `storageState`, and an expiry per site. The store lives in the
worker process. The key is `crawlerx:session:<site>` and its lifetime is capped
at 30 minutes. A replayed challenge forgets the session before the next
fallback attempt.

Configure login cookies with `CrawlerXFactory::configure($logins)`, where
`$logins` implements `LoginCookieProvider::cookiesFor(string $site): array`.
Provider cookies are attached to every request for that site. A persistent
login wall returns `auth_required` and is not retryable.

`PLAYWRIGHT_URL` is the one Playwright container for the node. `FLARESOLVERR_URL`
is the one FlareSolverr container. Playwright runs the `node` binary on PATH
when `PLAYWRIGHT_URL` is empty.
`CRAWLERX_USER_AGENT` sets the user agent. The default is a modern Chrome user
agent. `CRAWLERX_USER_AGENT_POOL` accepts a comma-separated pool; after three
consecutive challenges for a site, CrawlerX switches to the next user agent and
keeps it sticky in that site's session.

## Host throttling

Adapters may set `runtime.defaultThrottle` with `default_gap_seconds`,
`min_gap_seconds` and `max_gap_seconds`. CrawlerX clamps the configured gap to
those bounds and spaces requests by host and node. `CRAWLERX_NODE` identifies a
node; when unset, the hostname is used. A wait that cannot fit within the
remaining fetch budget returns retryable `rate_limited` with `Retry-After`
instead of sending a request.

The default throttle state is held by the worker process. To share it across
workers on one node, pass a PSR-16 cache as the second argument to
`CrawlerXFactory::configure($logins, $throttleCache)`. Cache keys use a
namespaced hash so they stay within PSR-16's portable key rules. PSR-16 has no
atomic reservation operation, so closely concurrent workers may occasionally
start within the same interval. See the [PSR-16 key and method requirements](https://www.php-fig.org/psr/psr-16/).

## Budgets

The default total budget is 150 seconds. Individual method caps are 20 seconds
for HTTP, 45 seconds for browser methods and 60 seconds for FlareSolverr.
`FetchOptionsDto::deadlineSeconds` can set a shorter consumer deadline; the
browser and FlareSolverr process timeouts are bounded by the remaining method
budget, and no method starts after that deadline.

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
