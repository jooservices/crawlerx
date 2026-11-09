# ADR-005: Deptrac was initially report-only

- **Status:** Superseded
- **Context:** The initial 1.7 decision was to report Deptrac violations without
  blocking CI while the layer boundaries were introduced. That policy no longer
  describes the current workflow, and the current configuration has no recorded
  `AbstractType`-to-Fetch debt.
- **Decision:** The historical report-only policy is superseded. CI runs
  `composer lint:deptrac` in `Architecture (Deptrac)` without
  `continue-on-error`; the required `Coverage upload` check fails unless the
  architecture job succeeds. Deptrac failures therefore fail the required CI
  workflow check.
- **Consequences:** The declared layer rules are enforced by CI. Run
  `composer lint:deptrac` locally and keep dependencies within those rules.
- **References:** [`deptrac.yaml`](../../deptrac.yaml),
  [CI architecture job](../../.github/workflows/ci.yml),
  [`CONTRIBUTING.md`](../../CONTRIBUTING.md).
