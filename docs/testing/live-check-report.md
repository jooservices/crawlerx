# Live adapter health report

`composer live-check:report` manually crawls the registered adapter manifests and
writes timestamped Markdown and JSON reports under `build/live-check/`. This is
a live network command; CI does not invoke it.

The report checks at most one listing sample and one detail sample per adapter.
It reads URLs and crawl types from `runtime.fixtureSamples`, preferring
`listing`/`performer_listing` and `detail`/`performer_detail` samples. If an
adapter has no detail sample, a `gallery` sample fills that role. An adapter
without a supported listing sample is checked only with its available sample.
Each adapter has a budget of three outbound requests. Robots lookups, follow-up
URLs, retries, and redirect hops all use that budget. The robots policy lookup
is cached per host for the run, so only the first sample for a host may need to
spend a request on it. Requests to the same hostname are at least 10 seconds
apart, including when separate adapters share a host. The entire run also has
a hard cap of 300 outbound requests. Requests over either cap are stopped
before reaching the network; a selected sample that cannot start within its
adapter budget is reported as `not-checked` with error code `request_limit`.

The live-check command uses the HTTP-only fetch chain. Browser and solver
methods can send additional requests outside CrawlerX's HTTP transport, so the
command does not invoke them. Redirects are followed through the same
per-request limiter when the client can preserve its DNS pinning checks. A
redirect that needs a cross-host DNS pin is reported as `not-checked` with error
code `redirect_unsupported` because the request-limited client cannot pass the
verified addresses to the transport. When an adapter's manifest configures a
non-HTTP fetch profile and its HTTP-only check receives a challenge, the report
records `not-checked` with error code `fallback_unsupported`; the configured
fallback could not be tried. Other observed failures retain their categories,
such as `parse-error`, `network`, `empty`, or `blocked`.

An optional `runtime.live` block can override a fixture sample when the
fixture URL is no longer suitable for a manual live check:

```json
{
  "runtime": {
    "live": {
      "listing": {
        "url": "https://example.test/catalog/",
        "type": "listing",
        "name": "catalog"
      },
      "detail": {
        "url": "https://example.test/item/123",
        "type": "detail"
      }
    }
  }
}
```

The `name` field is optional. Types must be supported by the adapter's
`adapter.capabilities` list. The `listing` and `detail` keys accept the same
crawl types as the matching fixture samples, including performer types.

Run all adapters or limit a check to one slug:

```sh
composer live-check:report
composer live-check:report -- --only=javbus
composer live-check:report -- --only=javbus --proxy=http://127.0.0.1:8080
```

The proxy may also be passed as `--proxy URL`. It sets CrawlerX's HTTP fetch
proxy; browser and solver sidecars use their own proxy configuration. Proxy
credentials are not included in either report. Results are classified as `ok`,
`empty`, `blocked`, `parse-error`, `network`, or `not-checked`. The command exits with a
non-zero status when any sample is not `ok`.
