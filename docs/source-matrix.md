# Source matrix

Run the source matrix with the services configured in `.env` (`PLAYWRIGHT_URL`, `FLARESOLVERR_URL`) and the PHP runner in `CRAWLERX_PHP` (see `.env.example`). The command starts nothing and creates no containers:

```sh
make source-matrix
```

Own Docker services instead: `make fetch-up`, use the Docker values from `.env.example`, then run the same command.

To check one adapter, pass its manifest slug:

```sh
make source-matrix SITE=javlibrary
```

The command reads each adapter capability and its matching `runtime.fixtureSamples` URL. It follows the same `CrawlerX::url(...)->site(...)->type(...)->tryCrawl()` path as the live check. It writes machine-readable rows to `build/source-matrix.json` and the sorted summary to `artifacts/reports/source-matrix.md`.

Each result contains the fetch method and HTTP status, challenge flag, parsed and required field counts, verdict, and mapped reason. The report does not store fetched HTML or exception text. The summary counts verdicts and `CrawlErrorCode` values and states source coverage; a full run should report `25/25`.

Requests run sequentially. The tool caps fetch attempts at three per source and capability, reserving one request for a single timeout/network retry after a 15-second cooldown. It waits at least five seconds between requests to a host, while preserving a larger manifest throttle. A timeout or network error that remains after retry is degraded. Blocked is reserved for authentication requirements, HTTP 401/403, unresolved challenges, and explicit blocked responses. Adapters requiring login are reported as blocked without sending a request. The tool fills the run metadata itself (UTC times, git SHA, CPU count, load averages of the machine that runs PHP) and finalizes the report in the same process; the `CRAWLERX_MATRIX_*` variables still override these values.

The report header records the UTC start/end times, tool git SHA, CPU count, three load averages at start/end, and enabled fetch methods. A start load above the CPU count is reported as a console and report warning.

`working` means the fetch succeeded and all required fields were present. `degraded` means fields were missing or another mapped error occurred. `blocked` means a login requirement, HTTP 401/403, a challenge that the configured fetch chain did not pass, or an explicit blocked response. Adapter changes remain separate work.

Run it one at a time per worktree. The report files under `build/` are overwritten by each run, and concurrent live runs would also break the per-host politeness limits (at most 3 requests per site and capability, 5 seconds between requests to a host).
