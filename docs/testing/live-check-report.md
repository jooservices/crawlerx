# Live adapter health report

`composer live-check:report` manually crawls the registered adapter manifests and
writes timestamped Markdown and JSON reports under `build/live-check/`. This is
a live network command; CI does not invoke it.

The report checks at most one listing sample and one detail sample per adapter.
It reads URLs and crawl types from `runtime.fixtureSamples`, preferring
`listing`/`performer_listing` and `detail`/`performer_detail` samples. If an
adapter has no detail sample, a `gallery` sample fills that role. An adapter
without a supported listing sample is checked only with its available sample.
The limit applies at the outbound HTTP request boundary, so follow-up URLs,
fetch retries, and `robots.txt` requests use the same two-request adapter budget.
Requests to the same hostname are at least 10 seconds apart, including when
separate adapters share a host. A request over the adapter budget is stopped
before it reaches the network and returned as a rate-limited response.

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
`empty`, `blocked`, `parse-error`, or `network`. The command exits with a
non-zero status when any sample is not `ok`.
