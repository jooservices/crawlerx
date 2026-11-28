# ADR-007: Require full line and branch coverage for new and changed code

- **Status:** Proposed
- **Context:** The workspace engineering standard (owner decision of 2026-11-14) requires
  100% of lines and of branches of every source file that is written or changed, with every
  test passing. CrawlerX enforces less, and does not measure branches at all:
  - [`tools/coverage-enforce.php`](../../tools/coverage-enforce.php) fails a file below
    85% line coverage (`$threshold = 85.0`).
  - The `coverage:check` script in [`composer.json`](../../composer.json) also requires 90%
    line coverage on the union of the Unit and Feature suites (`coverage-union.php ... 90`).
  - Coverage is collected as Clover by the `php` image, which [`Dockerfile`](../../Dockerfile)
    builds with PCOV, and by the sharded CI jobs
    ([workflow](../../.github/workflows/ci.yml): `--coverage-clover`). PCOV reports line
    coverage only, so no branch figure exists for any gate to read.
- **Decision (proposed):**
  1. New and changed source files must reach 100% line and 100% branch coverage.
  2. Existing files keep the current 85% per file and 90% union thresholds, and a file may
     not fall below the coverage it has.
  3. Collect branch coverage with a driver that supports it (for example Xdebug in path and
     branch mode, or another driver chosen when this is implemented) and fail CI on the
     changed files.
- **Consequences:** Nothing changes until this is implemented; that work changes the CI image
  or job, `tools/coverage-enforce.php` and possibly the test run time, and is a separate
  ticket. Until then the 85% and 90% thresholds are the only enforced coverage gates.
- **References:** [`tools/coverage-enforce.php`](../../tools/coverage-enforce.php),
  [`composer.json`](../../composer.json), [`Dockerfile`](../../Dockerfile),
  [CI workflow](../../.github/workflows/ci.yml).
