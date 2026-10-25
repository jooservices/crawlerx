# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Bound a fetch and its fallbacks to 60 seconds by default, retain every failure attempt, and remove curl watchdog grace time (JOOXCR-260).
- Recognize removed AVfanProfiles pages, refresh its live profile sample, and wait for DUGA product content rather than navigation links (JOOXCR-260).
- Try the proven solver before a slow browser on Jable/JAVLibrary, and let the canary use project-configured browser endpoints (JOOXCR-260).

## [1.4.2] - 2026-10-24

### Documentation

- Fetch strategy: a status log of sources that are down or walled; warashi is offline (Dedibox maintenance, port 443 refused) and is not behind a challenge (JOOXCR-244).

### Fixed

- JavBus: every fetch sends the age confirmation cookie (`dv=1`), so the site returns its pages instead of the "Age Verification JavBus" wall that was reported as a challenge on every method (JOOXCR-243). `existmag=all` makes the pages list movies with and without magnets.

### Security

- Node tooling: override the transitive `zod` to 4.6.6 (GHSA-mrhx-c7xc-m3w5, High);
  puppeteer, puppeteer-extra, the stealth plugin and playwright still load (JOOXCR-250).

## [1.4.1] - 2026-10-19

### Fixed

- MissAV: the listing follows the next page link when it carries a mirror prefix (`/dm539/en/new?page=2`).
- Jable: the models index follows its numbered pages; the current page is read from the active pager entry.

## [1.4.0] - 2026-10-19

### Added

- Per-site field completeness: every site's pages were compared with the live site and now return every offered field. Movie details (JavDB, JavBus, Caribbeancom, Duga, FC2, Heyzo, Tokyo-Hot, MissAV, ffjav, Jable, JavLibrary, XCity, 1Pondo, 141jav, onejav, javbtc, MinnanoAV), list cards (price, size, date, duration, actresses, magnets where the card has them), performer profiles (Aisex, javdatabase, avjoho, avfan_profiles, warashi, JavBus, XCity, MinnanoAV) and galleries (warashi related scene).
- Standard metadata keys and builders for movie data (`Support\MovieMeta`, `docs/dto-conventions.md`): maker, label, series, director, rating, magnets, downloads, sample video, price, titles, posted date.
- Performers inside a movie carry `{external_id, name, url}` where the site provides them.
- Dev VM browser and FlareSolverr services configured only through `.env` (`scripts/docker/*-vm`), `CRAWLERX_DISABLED_SITES`, `fetch.settleAfterReadyMs` and `browserHeaders` in the adapter manifest.

### Changed

- ffjav: `date` is now the release date (発売日); the post date moved to `metadata.posted_date`.
- XCity reads both Japanese and English pages (plain requests are answered in Japanese); profile fields were lost on Japanese pages before.
- FC2 is requested in Japanese and the description is read from its description widget (session cookie and referer are sent).
- Tokyo-Hot and Heyzo covers are the real cover image (not the site logo); Heyzo values built by page scripts are read from the script text.
- Jable returns every model including hashed url slugs (the name comes from the avatar tooltip) and the release date.
- JavLibrary: `maker`, `label`, `director` use the standard metadata keys (`publisher` is now `label`).
- Fetch chain: FlareSolverr also runs when the browser step got no answer at all; a normal page that only references Cloudflare is no longer treated as blocked.
- The shared Onejav-theme card reader serves 141jav, onejav and ffjav (detail and list cards).

### Removed

- The unused generic `Shared\Catalog\Detail` and `Listing` parsers (every catalog site has a dedicated parser).

### Also in this release

- Standard metadata keys and builders for movie data (`Support\MovieMeta`, `docs/dto-conventions.md`).

- Caribbeancom returns every field of the movie page (starring with ids, all tags, release date, duration, poster, sample video, every download size per resolution, the whole photo gallery with a sample flag) and of the listing cards (title, cover, date, performers); the performer no longer comes from a menu link.
- JavBus returns every field of the movie page (ID, date, length, director, studio as maker, label, series, genres, performers with ids, sample images, all magnets with size, date and HD/subtitle flags), the listing cards (date, flags), the performer pages (date of birth, age, height, cup, bust, waist, hips, hobby, birthplace, filmography) and the performer listing avatar; English, Chinese and Japanese labels are understood.
- Add `fetch.settleAfterReadyMs` to the adapter manifest: wait for the network to go quiet after the ready marker (pages that load data with AJAX, such as the JavBus magnet table).
- JavDB returns every field of the movie page (date, duration, director, maker, series, rating and votes, tags, performers with ids and gender, preview images, all magnets, want/seen counters) and of the listing cards (rating, date, flags), without navigation items; pages are requested in English (Chinese labels are still understood).
- Configure the browser and FlareSolverr services, and the PHP runner for `make`, only through `.env`
  (`.env.example`); Docker is optional and keeps its current addresses as defaults.
- `make live-check`, `source-matrix`, `bench` and `canary` no longer start Docker services; the source matrix
  fills and finalizes its run metadata itself and creates no containers or networks.
- Add `PLAYWRIGHT_HOST` to the browser service so it can bind the loopback behind a tunnel.
- Run FlareSolverr when the browser step gets no answer at all (timeout), not only after a detected challenge.
- Stop treating a normal page that merely references Cloudflare (a Turnstile script) as blocked: adapters now use the same challenge detection as the fetch layer. Fixes the MinnanoAv filmography listing.
- Parse the MinnanoAv movie page fully (product code, release date, cover, performers, maker, label, sample video) and the filmography rows (cover, date, performers, maker, label).
- Report a missing browser setup as "Browser service is not configured" instead of a Node stack trace.

## [1.3.1] - 2026-10-06

### Changed

- Require `jooservices/client` `^4.4.1`; this includes the redirect-cookie
  domain scoping fix and excludes the affected `4.4.0` release.
- Keep fetch sessions in the worker process and configure the Playwright and
  FlareSolverr services with `PLAYWRIGHT_URL` and `FLARESOLVERR_URL`.
- Treat Avfan Profiles (`av-fan.tokyo`) as public for the covered views and
  refresh the 63-sample live canary status.

### Migration

- Remove the `cache:` argument from `CrawlerXFactory::configure()`. Set
  `PLAYWRIGHT_URL` and `FLARESOLVERR_URL` for service containers; `CRAWLERX_NODE`
  is removed, and an empty `PLAYWRIGHT_URL` runs the `node` binary on `PATH`.

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

[1.3.1]: https://github.com/jooservices/crawlerx/releases/tag/v1.3.1
[1.3.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.3.0
[1.2.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.2.0
[1.1.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.1.0

[1.3.1...1.3.0]: https://github.com/jooservices/crawlerx/compare/v1.3.0...v1.3.1
[1.3.0...1.2.0]: https://github.com/jooservices/crawlerx/compare/v1.2.0...v1.3.0
