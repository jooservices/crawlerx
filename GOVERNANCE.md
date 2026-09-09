# Governance

## Project model

`jooservices/crawlerx` is an owner-driven JOOservices project. The owner makes
final decisions on scope, architecture, access, and releases.

| Role | Responsibility |
| --- | --- |
| Owner / lead maintainer | Viet Vu (JOOservices): roadmap, security response, architecture, release approval, and final arbitration |
| Maintainers | Review pull requests, uphold the quality gate, and maintain provider fixtures |
| Contributors | Propose focused changes through issues and pull requests |

## Decision and release policy

- Normal changes are discussed and merged through pull requests under
  [CONTRIBUTING.md](CONTRIBUTING.md).
- New providers and fetch strategies must have a current product need, a
  maintainable parser, live-source fixtures, and deterministic tests.
- Releases require explicit owner approval. A release branch merges to
  `master`, is tagged from `master`, and then merges back to `develop`.
- Quality gates and branch protections may only be relaxed with owner approval.

## Conduct and amendments

Conduct reports follow [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Changes to
this governance document require an owner-approved pull request.
