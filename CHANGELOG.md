# Changelog

All notable changes to this package are documented in this file. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
package follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

No unreleased changes.

## [1.0.0] - 2026-09-09

### Added

- Catalog providers for 10musume, Pacopacomama, Muramura, Kin8tengoku, MOODYZ,
  IDEAPOCKET, S1, and Madonna.
- Performer-directory providers for T-Powers, Mine's, Bstar, and SOFT ON
  DEMAND.
- Captured live-site fixtures for the added providers and deterministic tests
  that exercise them without network access.
- URL-driven crawl orchestration with typed DTO results, adaptive fetch
  fallbacks, browser-service integrations, and 32 catalog/performer providers.

### Changed

- GitHub Actions now uses GitHub-hosted runners and the repository includes the
  JOOservices community, governance, workflow, and contribution documents.
