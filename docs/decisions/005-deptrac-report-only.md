# ADR-005: Keep Deptrac report-only in CI

- **Status:** Accepted
- **Context:** `deptrac.yaml` records the intended Adapters, Fetch, Dto,
  Support, and Contracts dependencies. The current tree still has a known
  Adapters-to-Fetch dependency.
- **Decision:** Run Deptrac in CI and publish violations as a warning, without
  making the architecture job a required check yet. Keep the intended rules in
  the configuration so the report exposes boundary drift.
- **Consequences:** Contributors can see violations, but passing CI does not
  prove that every dependency follows the declared layers.
- **References:** [`deptrac.yaml`](../../deptrac.yaml),
  [CI architecture job](../../.github/workflows/ci.yml),
  [`CONTRIBUTING.md`](../../CONTRIBUTING.md).
