# ADR-004: Keep response caching disabled by default

- **Status:** Accepted
- **Context:** Source pages can change quickly, and storing fetched responses
  changes freshness and storage behavior for the consumer.
- **Decision:** Do not create a response cache unless a PSR-16 cache is passed
  to `CrawlerXFactory::configure()` and `CRAWLERX_RESPONSE_CACHE_TTL` is a
  positive number. The cache applies to successful HTTP responses; other fetch
  methods are not cached by this decorator.
- **Consequences:** The default request path avoids response-cache storage and
  cache staleness. Consumers who opt in choose the cache backend and lifetime.
- **References:** [`FetchRuntimeConfig`](../../src/Fetch/FetchRuntimeConfig.php),
  [`CrawlerXFactory`](../../src/CrawlerXFactory.php),
  [`ResponseCache`](../../src/Fetch/Cache/ResponseCache.php),
  [`.env.example`](../../.env.example).
