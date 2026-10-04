# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-10-04

### Added

- Browser sidecar and isolated fetch lab, with browser dependencies derived
  from the package lockfile (PR #50).
- A fetch-strategy contract with structured error values for `not_found`,
  `gone`, `rate_limited`, `timeout`, `challenge`, `network`, and
  `auth_required`, plus retryability metadata (PR #51).
- Benchmark and live-canary tooling for measuring configured fetch strategies
  and checking representative adapters (PR #52).
- Reusable browser processes across fetch requests, reducing repeated Chromium
  startup work (PR #55).
- Shared session storage, FlareSolverr cookie replay, and a
  `LoginCookieProvider` for request-scoped authenticated sessions (PR #56).

### Changed

- Require `jooservices/client` `^4.4`; redirect cookies are enabled by the
  client by default and remain separate from CrawlerX's session store and
  explicit cookie handoff (PR #59).
- Enable the fetch lab's TC-M08 header-propagation case and make Compose host
  ports configurable or fully disableable for isolated runs; make CaptainHook
  work correctly from Git worktrees (PR #58).

### Fixed

- Apply configured `ClientBuilder` options when `ClientFactory` creates a
  client (PR #49).
- Refresh the Jable performer fixtures and readiness expectations (PR #53),
  and refresh the 141Jav live detail sample (PR #54).
- Refresh Aisex performer fixtures and update the pagination expectation for
  page 1214 (PR #57).

### Security

- Block browser-side requests to private, loopback, link-local, CGNAT,
  unique-local IPv6, unspecified, multicast, and metadata addresses, including
  redirects; support an optional hostname allowlist and keep private-network
  access limited to the fetch lab (PR #60). Blocked requests return the
  non-retryable `ssrf_blocked` error.

## [1.2.0] - 2026-10-01

### Added

- Warashi photo gallery crawling: the adapter now implements `GalleryCapable`
  and parses photo galleries under `/photo-gallery/` into `GalleryDto` with
  full-resolution `image_url`, `thumbnail_url`, position, performer names, and
  picture source.
- JavPhotos photo gallery adapter with `GalleryCapable` and `ListingCapable`:
  the listing page discovers gallery URLs (multi-hop via `nextCrawlType`), and
  each gallery parses photos with full-resolution `image_url`, `thumbnail_url`,
  position, performer names, tags, and the movie code from image paths.
- Avjoho performer database adapter (`db.avjoho.com`) with
  `PerformerListingCapable` and `PerformerDetailCapable`: the category listing
  is paginated, and each detail page parses debut date, birth date, height,
  B/W/H sizes, cup size, birthplace, blood type, hobby, aliases, exclusive
  makers, SNS, a bio text, and a profile image.
- Avfan Profiles performer adapter (`av-fan.tokyo`) with
  `PerformerListingCapable` and `PerformerDetailCapable`: cup-based listing
  yields performer URLs, and each detail page parses birth date, B/W/H sizes,
  cup size, birthplace, blood type, hobby, debut date, agency, official site,
  SNS links, and a profile image.
- Aisex performer adapter (`aisex.jp`, 60k+ actresses) with
  `PerformerListingCapable` and `PerformerDetailCapable`: the paginated
  listing yields performer URLs, and each detail page parses birth date,
  zodiac sign, blood type, height, bust, cup size, waist, hip, and a profile
  image.
- JOOservices community and workflow standards (`CODEOWNERS`,
  `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `GOVERNANCE.md`, `SECURITY.md`,
  `SUPPORT.md`, `WORKFLOWS.md`), an MIT `LICENSE`, and a Dependabot
  configuration with `dev-dependencies` groups.

### Changed

- Adapter count grows from 21 to 25: gallery capability now covers EPORNER,
  Warashi, and JavPhotos, and three bio-rich performer sources (Avjoho, Avfan
  Profiles, Aisex) join the existing performer adapters.

### Fixed

- JavPhotos performer names separated from keyword tags; image-only SNS links
  retained in Avfan Profiles performer details.

## [1.1.0] - 2026-09-30

### Added

- `CrawlItemResultDto::$nextCrawlType`: list items declare the crawl type to
  apply to their URL next, enabling multi-hop discovery until a terminal
  (detail) item is reached.
- `SearchCapable` and `PerformerSearchCapable` contracts, `CrawlType::Search` /
  `CrawlType::PerformerSearch`, and a `query` field on `CrawlRequestDto`.
- Manifest capability validation: adapters must implement every capability
  declared in their manifest (fail fast at registration).
- `AdapterManifestDto::entities()` entity-scoped capability view derived from
  the flat `capabilities` list.
- EPORNER photo gallery adapter with `GalleryCapable`, `GalleryDto`, `PhotoDto`,
  and `ScreenshotDto`.
- `PerformerOnlyCapabilities` shared concern for sites without movie pages.
- Live-captured fixtures replacing placeholder HTML across adapters.

### Changed

- XCITY performer discovery (index -> kana -> ini-listing -> detail) now tags
  each intermediate hop with `next_crawl_type` so consumers keep crawling to
  the terminal detail page.
- Jable performer listing and detail use real model-page parsers instead of
  aliasing the movie parser.
- Catalog adapters now return the raw site id as `external_id` while the movie
  `code` keeps the site prefix (HEYZO/FC2).
- CI runs on GitHub-hosted runners; Codecov, SonarQube, commitlint, semantic
  PR, gitleaks allowlist, and workflow audits are configured.

### Fixed

- Multi-hop performer discovery (XCITY, Jable) previously failed because
  intermediate listing URLs were treated as detail pages.
- Manifest capabilities could be declared without a matching adapter
  implementation (Jable performer listing was an alias to the movie parser).
- Catalog `external_id` for HEYZO/FC2 included the code prefix inconsistently.

[1.3.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.3.0
[1.2.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.2.0
[1.1.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.1.0

[1.3.0...1.2.0]: https://github.com/jooservices/crawlerx/compare/v1.2.0...v1.3.0
