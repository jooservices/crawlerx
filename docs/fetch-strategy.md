# Fetch strategy

CrawlerX chooses the least expensive fetch method that can return a usable page.
The strategy is configured by each adapter manifest under `runtime.fetch`.

## Profiles

| Profile | First method | Fallback behavior |
|---|---|---|
| `http_only` | HTTP | No browser fallback |
| `adaptive` | HTTP | Optional curl-impersonate, then Playwright, then FlareSolverr after a detected challenge or when no earlier method got an answer |
| `browser_likely` | Playwright | FlareSolverr after a detected challenge or when no earlier method got an answer |

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

Terminal results stop the fallback chain. FlareSolverr is called after a
detected challenge, and also when no earlier method got any answer from the site (a method could not run, or a
browser timed out on a page that never settles, as Cloudflare interstitials do). It is skipped only when an earlier
method received an HTTP status that was not a challenge.

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

`PLAYWRIGHT_URL` is the one browser service for the node and `FLARESOLVERR_URL` the one FlareSolverr service; both are plain addresses set in `.env`, so they can point at this project's Docker, the Workspaces Dev VM (through `scripts/docker/playwright-fetch-vm` and `scripts/docker/flaresolverr-vm`) or any other host. The browser service binds `PLAYWRIGHT_HOST` (default `0.0.0.0`, use `127.0.0.1` behind a tunnel). Playwright runs the `node` binary on PATH
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

The same configuration shares the per-host circuit state across workers on a
node. Ten consecutive non-terminal fetch failures open the circuit for five
minutes. The first request after that interval is a half-open probe; an
additional request waits for its 30-second probe lease. An open circuit returns
retryable `rate_limited` with the remaining time in `Retry-After`. Circuit cache
failures fall back to worker-local state. During one fetch, the circuit is
consulted at most once, after the first uncached method attempt passes host
throttling; later fallback attempts do not re-check it. Host throttling still
runs before every uncached method attempt.

## Robots.txt compliance

Before any fetch-method attempt, the chain consults the host's `robots.txt`
through `RobotsGuard`. On a cache miss, the guard fetches
`<scheme>://<host>/robots.txt` through the default `ClientFactory` HTTP client.
The scheme comes from the target URL, defaults to HTTPS when absent, and an
explicit port is omitted. Parsed rules are memoized in worker memory without
expiry. An unreachable file fails open as an empty ruleset, which is also
memoized, so a long-running worker does not retry it. The optional shared
PSR-16 cache stores the raw robots.txt body for one hour; it does not refresh
rules already held in worker memory. The guard evaluates the most specific
matching user-agent group, falling back to `*`; groups are not combined. This
request does not receive request or site proxy options and is not covered by
the browser-side SSRF guard; it may reach a private host before a browser
method later blocks the target. Use the process-level `CRAWLERX_HTTP_PROXY`
setting when the robots request must use a proxy. The rules follow RFC 9309:
the longest matching pattern wins, `*` is a wildcard, a trailing `$` anchors to
the end of the path, and an Allow of equal length overrides a Disallow. An
explicit Disallow aborts the request with a `RobotsDisallowedException`
(fail-closed); an unreachable `robots.txt` is logged and the request proceeds
(fail-open).

The guard is on by default. Disable it globally with
`CRAWLERX_RESPECT_ROBOTS=false`, or per site with
`runtime.defaultCrawlConfig.respect_robots: false` in the adapter manifest. An
explicit manifest value takes precedence in either direction; when omitted,
the runtime setting applies.

`Crawl-delay` is parsed per user-agent group and applied as an additional
minimum gap on top of the site's `defaultThrottle`.

## HTTP response cache

HTTP response caching is disabled by default. Set `CRAWLERX_RESPONSE_CACHE_TTL`
to a positive integer number of seconds and pass a PSR-16 cache as the third
argument to `CrawlerXFactory::configure($logins, $throttleCache, $responseCache)`
to enable it. The cache key includes the HTTP method, URL and request headers,
including cookies and the user agent. Only successful 2xx responses are cached;
HTTP errors, detected challenges and captcha pages are fetched again. Cache
hits bypass both the host circuit and throttle checks for that method attempt;
the earlier robots check still runs.

## Budgets

The default total budget is 60 seconds. Individual method caps are 20 seconds
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

## Sources that are down or walled (status log)

| Source | Checked | Result | Methods tested |
|---|---|---|---|
| warashi | 2026-10-24 | The site is offline, not behind Cloudflare. `warashi-asian-pornstars.fr` resolves to 51.159.55.143; port 443 refuses connections from WSL, the Dev VM browser services (FlareSolverr: `net::ERR_CONNECTION_REFUSED`) and a Mac; port 80 answers `503` with the page "Serveur Dedibox en maintenance". | http, flaresolverr (the Dev VM browser services cannot even connect, so playwright and stealth variants cannot do better). |
| javbus | 2026-10-24 | Not a Cloudflare challenge: the site answers 200 with its "Age Verification JavBus" page until the cookie `dv=1` is sent. The profile now sends `dv=1; existmag=all` (JOOXCR-243). | flaresolverr with the cookie returns the real listing. |

Re-check a source with `php artisan crawler:source-health --source=<slug>` in JavMeta before opening a new ticket.

### Source method order

An optional `runtime.fetch.chain` array overrides the profile's method order for
that adapter. Explicit consumer fetch options still take precedence. Jable and
JAVLibrary try HTTP, then FlareSolverr, then Playwright: the isolated canary
showed browser waits consuming 45 seconds before the solver could run. HTTP
also reuses an already established session without launching another browser.
Other adapters retain their existing profile order.

The budget includes retry backoff and host-throttle waits. Each transport receives
at most the remaining whole seconds; curl's process watchdog has no extra grace
period. Timeout/network failure metadata contains all completed attempts,
transport exceptions and the first fallback skipped when the budget is spent.
Custom fetch handlers must honor `methodTimeoutSeconds`; the chain cannot
preempt arbitrary PHP code inside a consumer-supplied synchronous handler.

## Outbound proxy

The HTTP fetch method can go through a proxy, for example a Japan exit for
sources that geo-block other countries. At the CrawlerX level, select it
process-wide with `CRAWLERX_HTTP_PROXY=http://host:port`, or per site profile
and per request with `HttpProfileDto::$proxy` and `HttpOptionsDto::$proxy`; a
request option wins over the profile, which wins over the environment. If
CrawlerX leaves libcurl's proxy unset, libcurl may still use `http_proxy` or
`all_proxy`; `NO_PROXY` may bypass a configured proxy, so an unset CrawlerX
proxy does not guarantee a direct connection. The default robots request does
not receive request or site proxy options. The proxy URL may contain credentials
and is never logged. Browser-based methods and FlareSolverr are not routed
through the CrawlerX HTTP page proxy. Playwright and Puppeteer use their
separate SSRF egress proxy; FlareSolverr requires separate destination or
network controls.
