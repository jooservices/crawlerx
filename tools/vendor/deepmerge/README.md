# deepmerge (CrawlerX patch)

Vendored from upstream `deepmerge@4.3.1` with the GHSA-gcqx-w48m-w2h4
prototype-poisoning guard from upstream PR #286 (`getKeys` skips `__proto__`).

Version is published locally as `4.3.2` so npm/OSV scanners no longer match the
unpatched advisory range (`>= 4.2.0, <= 4.3.1`). Remove this override when
upstream ships a fixed release on the public registry.
