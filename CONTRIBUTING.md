# Contributing

Thank you for contributing to `jooservices/crawlerx`.

## Requirements and setup

- PHP `^8.5`; the Makefile uses a matching host runtime and otherwise uses the
  pinned Docker image.
- Docker with Docker Compose for browser-fetch sidecars and for hosts that do
  not have PHP 8.5.

```bash
make build
make install
make validate
make lint
make test
make ci
```

Install the repository hooks after dependencies are available:

```bash
composer hooks:install
```

Never bypass hooks with `--no-verify`.

## Branch and pull-request workflow

- `master` is production and accepts only release or hotfix pull requests.
- `develop` is the integration branch for normal work.
- Create `feature/*`, `fix/*`, `refactor/*`, `docs/*`, or `chore/*` from the
  latest `develop`, then open a pull request to `develop`.
- Every change to a long-lived branch requires a pull request with all checks
  green. Do not push directly to `master` or `develop`.
- Releases use `release/<version>` from `develop`, merge into `master`, tag
  from `master`, then merge `master` back into `develop`.

## Commit convention

Use English Conventional Commits with an imperative, uppercase subject and no
trailing full stop:

```text
feat: Add a catalog provider
fix: Correct a performer profile selector
docs: Explain fixture provenance
```

Commit messages are checked locally and on every pull request.

## Quality and fixture rules

- Run the relevant format, lint, static analysis, and PHPUnit suites before
  pushing; `make ci` is the full local gate.
- Add focused tests for every behavior change. Fixtures must be captured from
  the live source and committed with only the content needed to reproduce the
  parser behavior. CI must remain network-free.
- Keep source changes strict, typed, and small. Follow SOLID, DRY, KISS, and
  YAGNI; do not add parser abstractions without a current need.
- Public API, provider, or fixture-capture changes must update the relevant
  README and documentation in the same pull request.

## Pull requests and issues

Describe what changed, why it is needed, and the commands used to verify it.
Keep the diff focused and resolve all review comments before merging.

Use [GitHub Issues](https://github.com/jooservices/crawlerx/issues) for bugs
and feature requests. Do not report vulnerabilities publicly; follow
[SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions are licensed under the
[MIT License](LICENSE).
