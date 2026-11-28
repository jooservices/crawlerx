# ADR-006: Gate cyclomatic complexity and duplication with a shrinking baseline

- **Status:** Proposed
- **Context:** The workspace engineering standard (`standards/engineering-principles.md`
  of the Workspaces repository, owner decisions of 2026-11-12 and 2026-11-14) sets two
  hard gates: cyclomatic complexity per function at most 10, and no duplicated block of
  100 tokens or more. Code that already breaks a gate is allowed only as debt in a
  baseline that can shrink and never grow; new and changed code meets the gates at once.
  CrawlerX enforces neither today:
  - [`phpmd.xml`](../../phpmd.xml) excludes `CyclomaticComplexity`, `NPathComplexity` and
    `ExcessiveMethodLength` from the `codesize` ruleset, so `composer lint:phpmd` cannot fail
    on them.
  - [`composer.json`](../../composer.json) has no duplication script. The
    [CI workflow](../../.github/workflows/ci.yml) uploads an analysis to SonarQube (step
    `Upload SonarQube analysis`), which measures duplication, but no SonarQube check is
    among the required status checks of `develop` or `master`, and
    [`sonar-project.properties`](../../sonar-project.properties) sets no duplication
    threshold, so no duplicated block can fail a pull request.
  - Measured on `develop` (2026-11-28) by running PHPMD with the `codesize` ruleset over
    `src` (`vendor/bin/phpmd src text codesize`): 75 methods at the rule's default report
    level of cyclomatic complexity 10 or more, and 69 with an NPath complexity above its
    default limit.
- **Decision (proposed):**
  1. Turn the complexity rules on for new and changed code, with a limit of 10 per function.
  2. Record the methods that exceed the limit today in a baseline file that CI refuses to
     grow; a change that touches such a method must bring it within the limit or leave the
     baseline entry unchanged.
  3. Add a duplication check with a 100-token threshold and the same baseline rule. The tool
     is chosen when this is implemented.
  4. Burn the baseline down module by module, one ticket per module.
- **Consequences:** Nothing changes until this is implemented; that work edits `phpmd.xml`,
  the CI workflow and `composer.json` and is a separate ticket. After it, a new complex or
  duplicated block fails CI at once, and the baseline shows the remaining debt as a number
  that only goes down.
- **References:** [`phpmd.xml`](../../phpmd.xml), [CI workflow](../../.github/workflows/ci.yml),
  [`composer.json`](../../composer.json), [`sonar-project.properties`](../../sonar-project.properties), [ADR-005](005-deptrac-report-only.md) (the same
  "enforce, do not report" direction for the layer rules).
