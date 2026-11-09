# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add a changed-file test runner with adapter, fetch and fixture targeting plus full-suite fallback (`composer test:changed`, `make test-changed`) (JOOXCR-473).
- Add `composer check:fixtures-meta` to validate captured fixture provenance metadata, with a baseline for known legacy offenders (JOOXCR-485).
- Add `composer check:manifests` to check adapter manifest keys, slugs, base URLs, classes and capabilities (JOOXCR-483).
- Add the MGStage source (`mgstage`): paged work listing (search, new releases, maker, series, genre, actor) and work detail with every labelled row, large cover with thumbnail, all sample stills with thumbnails, prices and sale end, reviews, rating, counters and the sample-video URL; the site needs a Japan exit (`CRAWLERX_HTTP_PROXY`) and the `adc=1` age cookie (JOOXCR-471).
- Add an optional outbound HTTP proxy for the HTTP fetch method (`CRAWLERX_HTTP_PROXY`, `HttpProfileDto::$proxy`, `HttpOptionsDto::$proxy`), off by default (JOOXCR-470).
- Add Deptrac architecture rules for the Adapters, Fetch, Dto, Support and Contracts layers (`deptrac.yaml`, `composer lint:deptrac`) and a report-only CI job (JOOXCR-466).
- Add an opt-in PSR-16 HTTP response cache with configurable TTL, challenge/captcha exclusions and throttle bypass on cache hits (JOOXCR-465).

- Add the shared parsing traits `ParsesUrlParts` and `CoercesValues` (external id and page-number extraction, scalar coercion) and migrate the exact duplicates in the Asagei, Avfan, Jable, JavDatabase, Javbtc, Missav, Smartflash, Wpb and Xcity adapters to them (no behaviour change, JOOXCR-462).
- Add the Wikidata (`wikidata`) performer adapter on the official Query Service and wbgetentities API: paged listing of Japanese pornographic film actors and item detail with ja/en labels, descriptions and aliases, birth date, height, Commons image (original and thumbnail), Wikipedia sitelinks and every statement (typed values, ranks, qualifiers, property and item labels), 2 requests a listing page and 1 + ceil(ids/50) per item (JOOXCR-456).
- Add the Asagei Plus (`asagei`) gravure adapter with paged tag/category/home listings and article galleries (every in-article photo, cover, categories, tags, dates, prev/next and source fields), captured fixtures and URL inference (JOOXCR-461).
- Add the WPB Web Gravure (`wpb`) adapter for Weekly Playboy (週プレNEWS) public gravure listing and gallery pages: cover at full resolution with thumbnails, profile, credits, tags, source fields and captured fixtures (JOOXCR-460).
- Add an opt-out `robots.txt` guard to the fetch pipeline: parse robots.txt per host with a TTL cache, evaluate the library user agent and `*` group, honour Disallow/Allow/Crawl-delay, fail-open on an unreachable robots.txt and fail-closed on an explicit Disallow; default on, disable per site or globally with `respect_robots` (JOOXCR-464).

### Changed

- Split the grab-bag `CoercesValues` and `ParsesUrlParts` traits into 13 single-responsibility concerns (`MatchesUrlPath`, `ReadsQueryParams`, `CoercesNumbers`, `CleansText`, ...), one file and one unit test each, update every adapter's imports and document the one-concern-per-helper rule (no behaviour change, JOOXCR-472).
- Migrate the remaining duplicated N–Z adapter helpers to the shared parsing and coercion concerns without changing crawl output (JOOXCR-469).
- Migrate the exact duplicate private helpers of the G-M adapters (Jable, Jav321, JavBus, JavDatabase, JavLibrary, JavPhotos, MinnanoAv) to the shared `ParsesUrlParts` and `CoercesValues` traits, adding `firstQueryParam`, `titleWithoutCode` and `integerStat` (no behaviour change, JOOXCR-468).
- Migrate adapters A–F onto the shared parsing helpers (`ParsesUrlParts`, `CoercesValues`, `ResolvesLinkUrls`) and drop their private duplicates; extend those helpers with `dateString`, `arrayValue` and `linkUrl` (unit-tested, no behaviour change, JOOXCR-467).
- Cut CI wall time by caching the PHP image, running security and sharded Unit/Feature suites in parallel after Validate, and keeping the required check names unchanged (JOOXCR-466).
- Split `FetchFallbackChain` (747 lines) into single-responsibility collaborators under `src/Fetch` (attempt log rows and method runner with transient retry, pre-fetch throttle/circuit guard, session and budget request shaping, ready-marker gate, login-wall detection, rejection builders, circuit reporting); the chain stays a thin orchestrator and fetch behaviour, config keys, exceptions and the SSRF guard are unchanged (JOOXCR-463).

### Fixed

- Raise the composer process timeout to 1800 seconds so the release gate (`composer ci`, full coverage run) is not cut off at the 300 second default (the v1.6.0 Release workflow failed on it) (JOOXCR-457).
- Browser SSRF guard drops loopback/unspecified decoy DNS answers and pins only remaining public addresses, so hosts such as Jable/MissAV with Cloudflare A records plus `127.0.0.1`/`0.0.0.0` decoys fetch normally while private-only answers stay blocked; blocked responses carry a clear `ssrfReason` into the fetch exception message (JOOXCR-399 / JOOXCR-363).

## [1.6.0] - 2026-11-08

### Added

- Complete the images of the uncensored paysite and WordPress/custom maker adapters: Heyzo locked-gallery and special-gallery thumbnails, Tokyo-Hot jacket and package images and the cover of products without a sample, DUGA full-size jacket and sample poster, FC2 original sample files, Rocket origin files instead of Jetpack copies, Aisex actress photos on cards and related actresses, Falenogroup maker logos without CDN recompression (JOOXCR-423).
- Audit Maker CMS family images (28 adapters, JOOXCR-423): lazy `data-original`/`data-lazy-src`/`srcset` fallbacks in the shared image reader and per-adapter regression counts for every slider, profile and listing cover image.
- Add the JAVMenu (`javmenu`) adapter with its own parser: paged work listings (sections, code prefixes, makers, series, genres, directors), work detail with code, dates, duration, director, maker, series, genres, idols, poster, magnets and stream lines, the paged performer index and performer pages with their work grid, in every interface language (JOOXCR-453).
- Add the AV-Actress (`avactress`) adapter: paged actress rankings and lists (popular, bust, young, new) and actress profiles with measurements, birthday, blood type, birthplace, hobbies and every listed work with its large and thumbnail covers and sample movie (JOOXCR-454).
- Add the DAHLIA (`dahlia`) adapter with its own parser: work listing with pagination, work detail with every labelled row, cover, all gallery stills with thumbnails, sample video and store links, performer roster and performer profiles (JOOXCR-415).
- Add the Wikimedia Commons (`commons`) adapter on the official MediaWiki API: gravure idol category listing, category and file galleries with every original, thumbnail and licence field, and idol person pages (JOOXCR-424).
- Add the Smart FLASH (`smartflash`) gravure adapter with paged listing and gallery crawls (every article photo from the article and its photo viewer, tags, source, author, credits, dates), captured fixtures and URL inference (JOOXCR-425).
- Add the Gravure-ch (`gravurech`) photo-set adapter with a paged listing and gallery detail (title, cast, tags, magazine and issue date, page count, cover image, labelled fields); the photo pages are member-only and are not crawled (JOOXCR-428).
- Add the Materiall (`materiall`) adapter with its own parser: work listing with pagination (cover, cast, reservation and distribution dates, store links) and work detail with every labelled row, cover, sample-video link and store links, captured fixtures and URL inference (JOOXCR-416).
- Add the DANDY GROUP (`dandy`: Dandy, Cosmos, Yuuyake and sister labels) adapter with complete listing and detail fields, captured fixtures and URL inference (JOOXCR-422).
- Add the Next11 (`next11`) adapter with its own parser: month and label/series/genre/cast listings with pagination, and movie detail with every labelled row, all jacket and sample-still images, sample-video embed and purchase links (JOOXCR-421).
- Add the Indies (`indies`, 桃太郎映像出版) adapter with month, label and genre listings, title details, actress profiles and the actress index, complete field extraction and captured fixtures (JOOXCR-418).
- Add the PLUM (`plum`) adapter with its own parser: listing cards with pagination and complete movie detail (code, date, duration, cast, categories, series, price, review number, barcode, gallery, sample video, purchase links) from captured fixtures (JOOXCR-417).
- Add the FALENO GROUP (`falenogroup`) adapter with its own parser: work listing with pagination, maker pages and work detail with every labelled field, captured fixtures and URL inference (JOOXCR-414).
- Add the V-AV (`vav`) adapter on the shared Maker CMS base with complete public movie and listing fields (the site has no actress pages), captured fixtures and URL inference (JOOXCR-413).
- Add the BOMB.tv (`bomb`) photo/gallery adapter: the public Plus idol index and idol pages with every public pack image (cover, previews, sample still), pack facts and captured Shift_JIS fixtures (JOOXCR-429).
- Add the MUKU (`muku`) maker-CMS source with complete movie and listing fields (JOOXCR-410).
- Add the MKO LABO (`mko`) adapter on the shared Maker CMS base with complete public movie, listing and actress fields, captured fixtures and URL inference (JOOXCR-409).
- Add the MIMAN (`miman`) adapter on the shared Maker CMS base with complete movie and date-listing fields, captured fixtures and URL inference (JOOXCR-408).
- Add the Hajime Kikaku (`hajimekikaku`) adapter on the shared Maker CMS base with complete public movie and listing fields, captured fixtures and URL inference (JOOXCR-407).
- Add the OPERA (`opera`) maker-CMS source with complete movie and listing fields, captured fixtures and URL inference; the site exposes no actress profile fields, so performer detail is not declared (JOOXCR-404).
- Add the BeFreeBe adapter on the shared Maker CMS base with complete public movie, listing and actress fields, captured fixtures and URL inference (JOOXCR-405).
- Add the E-BODY (`ebody`) maker-CMS source with complete movie, listing and actress fields (JOOXCR-403).
- Add the WANZ FACTORY adapter on the shared Maker CMS base with complete public movie, listing and actress fields, captured fixtures and URL inference (JOOXCR-398).
- Add the To-Satsu adapter on the shared Maker CMS base with complete public movie and listing fields, captured fixtures and URL inference (JOOXCR-412).
- Add the Tameike Goro (`tameikegoro`) maker-CMS source with complete movie, listing and actress fields.
- Add the DEEP'S (`deeps`) adapter with its own parser: paginated and filtered listings and movie details with every labelled field, captured fixtures and URL inference (JOOXCR-420).
- Add the ROCKET (`rocket`) source with own-markup work list (dated and taxonomy archives) and detail parsing of every labelled field, captured fixtures and URL inference (JOOXCR-419).
- Add the Honnaka adapter on the shared Maker CMS base (date index, date listings, movie detail with every labelled row, actress profile) with captured-fixture field assertions (JOOXCR-402).
- Add the kira*kira adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail with label, actress profile) with captured-fixture field assertions (JOOXCR-399).

### Security

- Upgrade `symfony/config`, `symfony/filesystem` and `symfony/yaml` to 8.1.11 for the 2026-11-04 advisories (PKSA-9myg-zzdn-22rg, PKSA-g499-cm4q-v35y, PKSA-nktx-f1n1-b4yd); transitive `symfony/process`, `symfony/deprecation-contracts` and `symfony/polyfill-mbstring` move with them (JOOXCR-401).

### Changed

- Complete the images of the torrent/streaming indexes and remaining adapters (Missav, Jable, Javbtc, Onejav family, Avfan, Aisex, Avjoho, AvfanProfiles, Duga, FC2): resolve lazy-loaded covers, give the full-size cover next to its thumbnail, add the listing covers and performer photos the pages show, drop CDN re-compression and stop using a work screenshot as a performer photo (JOOXCR-423).
- Register follow-up fixtures from manifests (`runtime.fixtureSamples[].followUps`) and derive adapter-count assertions from the manifest directory, so adding a source no longer edits shared tests or docs (JOOXCR-392).
- Complete the images of the community-database and photo/gallery adapters (JOOXCR-423): JavBus cast photos and listing ids with an underscore, JavLibrary preview stills, JavDatabase and MinnanoAv portraits, lightbox stills and works jackets, Avjoho/AV-Fan/XCITY/Warashi card portraits, Warashi gallery cover and cast photo, Jav321 sample-video poster, with image-count tests over every real fixture.

## [1.5.0] - 2026-11-03

### Added

- Add the Fitch adapter on the shared Maker CMS base with complete movie, date/label/genre/series/release/reserve/search listing and actress fields, captured fixtures and URL inference (JOOXCR-356).
- Add the ATTACKERS adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail, actress profile) with captured-fixture field assertions (JOOXCR-355).
- Add the Pacopacomama listing/detail adapter on the shared 1Pondo-family base with real JSON fixtures and public sample galleries (JOOXCR-309).
- Add the caribbeancompr adapter (English and Japanese detail, English listing) on the shared Caribbeancom listing/detail parsers through a per-site `Profile`; `caribbeancom` output is unchanged (JOOXCR-351).
- Complete shared OnePondoFamily list/detail field extraction, retain full file/category/performer source records, and guard every family fixture against silent field loss and changes to existing DTO values (JOOXCR-360).
- Add the jav321 adapter (listing, detail with cast, genres and all images, performer listing) (JOOXCR-350).
- Add the 10musume adapter on the shared 1Pondo-family JSON base (listing + detail) (JOOXCR-308).
- Add the Muramura adapter on the shared 1Pondo-family JSON base (listing + detail) (JOOXCR-310).
- Add configurable Maker CMS family parsing with the S1 adapter, complete movie/actress metadata and captured-fixture field assertions (JOOXCR-348).
- Add the MOODYZ adapter on the shared Maker CMS base (date index, date listing, detail, actress) using the shared `metadata.label` field (JOOXCR-352).
- Add the Premium Beauty adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail with label, actress profile) with captured-fixture field assertions (JOOXCR-359).
- Add the IDEAPOCKET adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail with label, actress profile) with captured-fixture field assertions (JOOXCR-353).
- Add the ROOKIE adapter on the shared Maker CMS base (date index, date listings, movie detail with every labelled row, actress profile) with captured-fixture field assertions (JOOXCR-400).
- Add the BI adapter on the shared Maker CMS base (date index, date listings, movie detail with label, director, formats, sample video and purchase links, actress profile) with captured-fixture field assertions (JOOXCR-393).
- Add the DASDAS adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail, actress profile) with captured-fixture field assertions (JOOXCR-357).
- Add the Madonna adapter on the shared Maker CMS base (date index, date listing, detail with label/series/formats/purchase links, actress profile) with captured-fixture field assertions (JOOXCR-354).
- Add the HHH adapter on the shared Maker CMS base (date index, date listing, detail with label/series/formats/purchase links; unlinked actress names kept) with captured-fixture field assertions (JOOXCR-394).
- Add the MVG adapter on the shared Maker CMS base (date index, date/genre/series listings, movie detail with plain-text performers, formats, purchase links) with captured-fixture field assertions (JOOXCR-395).
- Add the kawaii* adapter on the shared Maker CMS base (date index, date listings, detail with label/series/formats/purchase links, actress profile) with captured-fixture field assertions (JOOXCR-358).
- Add the OPPAI adapter on the shared Maker CMS base (date index, date/genre listings, detail with label/formats/purchase links, actress profile) with captured-fixture field assertions (JOOXCR-396).
- Add the Nanpa Japan adapter on the shared Maker CMS base (date index, date/genre/series listings, movie detail with every labelled row, series, director, formats, sample video and purchase links) with captured-fixture field assertions (JOOXCR-411).
- AV-Wiki listing and detail support, using one `_embed=1` REST request per listing page or detail post (categories and tags come from `_embedded['wp:term']`), a 45 s timeout and a 12 s host throttle for the site's per-request delay, plus captured source fixtures (JOOXCR-349).
- Configurable `connect_timeout` (manifest `defaultCrawlConfig`) for hosts that delay the connection phase; it defaults to the previous fixed 5 s.
- Add the BIBIAN (ビビアン) adapter on the shared Maker CMS base (date index, date/genre/label/series listings, movie detail with every labelled row, actress profile) with captured-fixture field assertions (JOOXCR-406).

### Changed

- Extract configurable 1Pondo-family JSON parsing and crawler base while preserving OnePondo DTO output (JOOXCR-307).

### Security

- Upgrade `phpunit/phpunit` to 13.4.6 (CVE-2026-119193), override the `minimatch` npm dependency to 11.0.4, and ignore only the unfixable `deepmerge` advisory (GHSA-gcqx-w48m-w2h4) in `osv-scanner.toml` until 2026-12-31 (JOOXCR-320).

## [1.4.3] - 2026-10-26

### Fixed

- Bound a fetch and its fallbacks to 60 seconds by default, retain every failure attempt, and remove curl watchdog grace time (JOOXCR-260).
- Recognize removed AVfanProfiles pages, refresh its live profile sample, and wait for DUGA product content rather than navigation links (JOOXCR-260).
- Try the proven solver before a slow browser on Jable/JAVLibrary, and let the canary use project-configured browser endpoints (JOOXCR-260).

### Documentation

- Canary: refresh the 1.4.x status after the fetch-budget and source-fallback changes (57/68 samples pass), and publish the observed failures in `docs/testing/canary-latest.json`. Shared-service load is a possible, unconfirmed timing factor (JOOXCR-182).

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
