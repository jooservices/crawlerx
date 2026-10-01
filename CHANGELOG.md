# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[1.1.0]: https://github.com/jooservices/crawlerx/releases/tag/v1.1.0