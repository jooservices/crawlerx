# Source matrix

Run the source matrix from a worktree after building the CrawlerX PHP image and installing Composer dependencies in that image:

```sh
make source-matrix-image
docker run --rm --volume "$PWD:/app" --workdir /app jooservices/crawlerx:php85 composer install --no-interaction --prefer-dist
make source-matrix
```

To check one adapter, pass its manifest slug:

```sh
make source-matrix SITE=javlibrary
```

The command reads each adapter capability and its matching `runtime.fixtureSamples` URL. It follows the same `CrawlerX::url(...)->site(...)->type(...)->tryCrawl()` path as the live check. It writes machine-readable rows to `build/source-matrix.json` and the sorted summary to `artifacts/reports/source-matrix.md`.

Each result contains the fetch method and HTTP status, challenge flag, parsed and required field counts, verdict, and mapped reason. The report does not store fetched HTML or exception text. The summary counts verdicts and `CrawlErrorCode` values and states source coverage; a full run should report `25/25`.

Requests run sequentially. The tool caps fetch attempts at three per source and capability and waits at least five seconds between requests to a host, while preserving a larger manifest throttle. Adapters requiring login are reported as blocked without sending a request. The target starts only the project fetch-profile Node and FlareSolverr services in a unique Compose project, uses the project PHP image for the command, and removes those containers and their network when it exits.

`working` means the fetch succeeded and all required fields were present. `degraded` means fields were missing or another mapped error occurred. `blocked` is used for login requirements, forbidden responses, timeouts, and challenges that the configured fetch chain did not pass. Adapter changes remain separate work.
