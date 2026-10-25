# CrawlerX live canary

The canary is a manual, sequential live check. It is never run in CI and must
only be run with the owner's local `.env` present.

Create the local file from `.env.example` and add only the owner-provided
cookie values. `.env` is ignored by Git. The canary loads those values through
the `LoginCookieProvider` contract; cookie values are not printed or stored in
reports, snapshots, exceptions, or test output. User-agent settings are read
from `CRAWLERX_USER_AGENT` and `CRAWLERX_USER_AGENT_POOL`.

The Avfan adapter requires its owner-provided cookie. AvfanProfiles
(`av-fan.tokyo`) is checked without a cookie.

Run the complete canary with:

```sh
make canary
```

Limit a diagnostic run to one adapter with `make canary SITE=avfan`. A full
release gate must use the complete command and must not contain
`skipped_no_cookie` rows. Each adapter's registered `fixtureSamples` is
checked with the adaptive fetch chain. There is a one-second delay between
sites.

Every sample reports exactly one status: `ok`, `parse_failed`, `challenge`,
`auth_required`, `not_found`, `timeout`, `network`, or `skipped_no_cookie`.
Only `ok` is a pass, so the command exits non-zero for any other status.

Reports are written to `build/canary/<git-sha>-<timestamp>.json` and `.md`.
For a published status refresh, copy the full run's JSON report to
`docs/testing/canary-latest.json` and update `canary-status.md` from that same
report. Preserve the measured code revision and timestamp; a later documentation
commit does not change which revision was tested. Do not publish the empty
reports produced by the runner's unit tests as live results.
The JSON includes the winning method, every attempt, wall time, PHP CPU and
peak memory, and Docker `node`/`flaresolverr` CPU and peak RSS deltas.
Resource counters only cover containers visible to the runner's local Docker
probe; zero counters do not measure the load of remote Dev VM services.

After each full run, update
[`canary-status.md`](./canary-status.md) with the status, missing fields, and
XP item for every affected adapter/page type. A parser failure requires a
sanitized snapshot, a failing snapshot test, and a parser fix in an XP PR.
