# ADR-002: Respect robots.txt by default

- **Status:** Accepted
- **Context:** Crawls should observe a site's explicit robots rules and crawl
  delay unless an operator or source has a documented reason to opt out.
- **Decision:** Enable robots checking by default. `CRAWLERX_RESPECT_ROBOTS`
  can disable it at runtime; a source manifest's `defaultCrawlConfig.respect_robots`
  can explicitly choose the source default. An unavailable robots file fails
  open; an explicit matching `Disallow` blocks the crawl.
- **Consequences:** A crawl may stop before fetching a disallowed URL, and a
  robots crawl delay contributes to host throttling.
- **References:** [`FetchRuntimeConfig`](../../src/Fetch/FetchRuntimeConfig.php),
  [`SiteProfileDto`](../../src/Dto/SiteProfileDto.php),
  [`FetchFallbackChain`](../../src/Fetch/FetchFallbackChain.php),
  [`RobotsGuard`](../../src/Fetch/Guard/RobotsGuard.php).
