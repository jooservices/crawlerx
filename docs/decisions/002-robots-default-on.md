# ADR-002: Respect robots.txt by default

- **Status:** Accepted
- **Context:** Crawls should observe a site's explicit robots rules and crawl
  delay unless an operator or source has a documented reason to opt out.
- **Decision:** Enable robots checking by default. An explicit boolean value in
  a source manifest's `defaultCrawlConfig.respect_robots` overrides
  `CRAWLERX_RESPECT_ROBOTS` in either direction: `false` disables the guard
  even when the environment setting is true, and `true` enables it when the
  environment setting is false. When the manifest omits the value, the runtime
  setting applies. An unavailable robots file fails open; an explicit matching
  `Disallow` blocks the crawl.
- **Consequences:** A crawl may stop before fetching a disallowed URL, and a
  robots crawl delay contributes to host throttling.
- **References:** [`FetchRuntimeConfig`](../../src/Fetch/FetchRuntimeConfig.php),
  [`SiteProfileDto`](../../src/Dto/SiteProfileDto.php),
  [`FetchFallbackChain`](../../src/Fetch/FetchFallbackChain.php),
  [`RobotsGuard`](../../src/Fetch/Guard/RobotsGuard.php).
