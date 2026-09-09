# Security Policy

## Supported versions

The current unreleased rebuild on the `develop` branch receives security fixes.
The historical Packagist `v1.0.0` Laravel implementation is retired and is not
maintained by this repository.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability.

Use GitHub [private vulnerability reporting](https://github.com/jooservices/crawlerx/security/advisories/new), or email [admin@jooservices.com](mailto:admin@jooservices.com) with a summary, affected commit or version, impact, and a safe reproduction.

We will acknowledge, investigate, and coordinate a fix or disclosure as
appropriate. No response-time SLA is guaranteed.

## Scope

This policy covers package code, dependency and workflow configuration,
fixture-handling tooling, and the crawler's request/fetch behavior. It does
not authorize bypassing a source site's access controls or terms of use.

Automated checks include Composer audit, OSV Scanner, Gitleaks, Semgrep,
CodeQL for workflow files, and scheduled OpenSSF Scorecard analysis.
