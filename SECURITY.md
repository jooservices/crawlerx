# Security Policy

## Supported versions

| Version line | Status |
| --- | --- |
| `1.1.x` (this repository) | Current supported — receives security fixes |
| `v1.0.0` (retired Laravel implementation) | **End of life.** The archived previous implementation is a separate codebase lineage and receives no fixes |

The current rebuild is **not backward compatible** with the retired `v1.0.0`; security reports against the archived version cannot be actioned here.

## Reporting a vulnerability

**Do not open public GitHub issues for suspected vulnerabilities.**

Preferred: GitHub [private vulnerability reporting](https://github.com/jooservices/crawlerx/security/advisories/new) (Security Advisories).

Alternatively, email [admin@jooservices.com](mailto:admin@jooservices.com) with:

- a clear summary of the issue
- affected package version(s) / commit
- impact and expected risk
- reproduction details or proof of concept when available

If you are unsure whether something is security-related, contact us privately first rather than opening a public issue.

## What happens next

1. We acknowledge the report as soon as possible.
2. We investigate and validate, keeping you informed of progress.
3. Fixes land in the supported line with a coordinated disclosure; you are credited unless you prefer otherwise.

No guaranteed SLA is offered; handling time depends on severity, exploitability, and release risk.

## Scope

This policy covers repository-managed behavior, including:

- crawl and parse logic across site adapters
- URL detection and fetch fallback (HTTP, browser, FlareSolverr)
- DTO hydration / normalization and metadata handling
- fixture capture and the Docker / Compose tooling
- dependency and CI/security-workflow configuration that affects library consumers or repository integrity

Automated scanning runs on every change and on schedule:

- Composer audit and OSV Scanner (dependency vulnerabilities)
- Gitleaks (secrets)
- Semgrep OSS (PHP SAST)
- CodeQL (GitHub Actions workflow analysis)
- OpenSSF Scorecard and zizmor (workflow supply-chain audit)

## Non-security issues

Bugs, feature requests, questions, and documentation improvements belong in [GitHub Issues](https://github.com/jooservices/crawlerx/issues).