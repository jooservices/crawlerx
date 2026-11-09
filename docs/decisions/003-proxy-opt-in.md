# ADR-003: Keep outbound HTTP proxies opt-in

- **Status:** Accepted
- **Context:** Network routing differs by deployment and source. A hidden
  package-wide proxy would change the route for every caller.
- **Decision:** Use a proxy only when supplied by request options, a site HTTP
  profile, or `CRAWLERX_HTTP_PROXY`; otherwise the HTTP client connects
  directly. The browser's internal SSRF egress proxy is a separate security
  boundary and does not configure the HTTP proxy.
- **Consequences:** Consumers control proxy routing explicitly and can run
  without proxy configuration.
- **References:** [`HttpFetchHandler`](../../src/Fetch/Handlers/HttpFetchHandler.php),
  [`ClientFactory`](../../src/Services/ClientFactory.php),
  [`scripts/ssrf-proxy.mjs`](../../scripts/ssrf-proxy.mjs).
