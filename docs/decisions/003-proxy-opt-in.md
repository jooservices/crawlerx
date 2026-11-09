# ADR-003: Keep outbound HTTP proxies opt-in

- **Status:** Accepted
- **Context:** Network routing differs by deployment and source. A hidden
  package-wide proxy would change the route for every caller.
- **Decision:** For an HTTP page fetch, select the proxy from request options,
  the site HTTP profile, then `CRAWLERX_HTTP_PROXY`. The default robots request
  does not receive request or site proxies; use the process-level setting when
  it must also use a proxy. When CrawlerX leaves libcurl's proxy unset, libcurl
  may use `http_proxy` or `all_proxy`; `NO_PROXY` may bypass a configured
  proxy. An unset CrawlerX proxy therefore does not guarantee a direct
  connection. The browser's internal SSRF egress proxy is a separate security
  boundary and does not configure the HTTP proxy.
- **Consequences:** Consumers control CrawlerX's proxy selection at request,
  site, and process level, while transport environment variables can also affect
  routing.
- **References:** [`HttpFetchHandler`](../../src/Fetch/Handlers/HttpFetchHandler.php),
  [`ClientFactory`](../../src/Services/ClientFactory.php),
  [`scripts/ssrf-proxy.mjs`](../../scripts/ssrf-proxy.mjs).
