# GitHub Actions workflow flow

All workflows use GitHub-hosted `ubuntu-latest` runners. PHP checks execute in
the repository's Docker Compose PHP environment, so GitHub CI uses the same
PHP 8.5 image and Composer commands as a Docker-backed local workflow.

## Pull-request checks

Pull requests to `master` or `develop` run the following independent checks:

| Check | Workflow | Purpose |
| --- | --- | --- |
| Validate | CI | Build the PHP image, install dependencies, validate Composer metadata |
| Lint (Pint / PHPCS / PHPStan / PHPMD / PHP-CS-Fixer) | CI | Format, style, static analysis, and maintainability checks |
| Security (Dependencies / Secrets / SAST) | CI | Composer audit, OSV, Gitleaks, and Semgrep scans |
| Test (Unit / Feature) | CI | Network-free PHPUnit suites with Clover coverage |
| Coverage upload | CI | Enforce the 85% per-suite floor and retain the merged Clover artifact |
| Validate commit messages | Commitlint | Validate each commit against Conventional Commits |
| Validate PR Title | Semantic PR Title | Require the JOOservices PR-title convention |
| Analyze GitHub Actions | CodeQL | Analyze repository workflow code |

The `Coverage upload` job is the CI leaf gate: it cannot pass until lint,
security, and both test suites have passed.

## Bootstrap

The repository was seeded with verified `master` and `develop` roots before
branch protections could exist. Every subsequent change, including this
protection-verification update, arrives through a pull request with the full
required check set green.

## Push, scheduled, and release workflows

| Workflow | Trigger | Result |
| --- | --- | --- |
| CI post-merge | Push to `master` or `develop` | Validate and run the two coverage suites after integration |
| `scorecard.yml` (OpenSSF Scorecard) | Push to `develop`, weekly, manual | Upload Scorecard SARIF for supply-chain analysis |
| Link check | Weekly or manual | Check Markdown links |
| Stale | Daily or manual | Mark inactive issues and pull requests for maintenance |
| Workflow audit | Workflow-file changes, weekly, manual | Run Actionlint and Zizmor for GitHub Actions hygiene |
| Release | `v*.*.*` tag | Verify the tag is reachable from `master`, run the local CI gate, and create GitHub release notes |

## Security integrations and badges

GitHub Secret Scanning and Push Protection are repository security settings;
they are not controlled by an Actions file. Codecov and Sonar are intentionally
not configured for this recreated repository, so their badges and uploads are
not present. Add either only with its real project configuration and required
merge gate.

## Required branch checks

After an initial green pull request, branch protection requires the exact
checks listed under **Pull-request checks** for both `develop` and `master`.
Protections also require an up-to-date branch, a pull request, resolved
conversations, and block force pushes and deletions. Scheduled and manual
maintenance workflows are kept green but are not merge blockers.
