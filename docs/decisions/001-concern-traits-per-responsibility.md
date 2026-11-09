# ADR-001: Keep shared adapter concerns single-purpose

- **Status:** Accepted
- **Context:** Adapters share parsing, URL, and coercion helpers, but large
  multi-purpose traits make ownership and reuse difficult.
- **Decision:** Put each shared adapter helper in its own trait under
  `src/Adapters/Concerns/`. A trait has one responsibility, and adapters use
  only the traits they call. Give each new concern its own unit test.
- **Consequences:** Reuse stays explicit and focused; adding a shared helper
  includes a small new file and test.
- **References:** [`docs/adding-a-source.md`](../adding-a-source.md),
  [`src/Adapters/Concerns/`](../../src/Adapters/Concerns/).
