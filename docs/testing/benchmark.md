# CrawlerX benchmark

The benchmark is a local measurement tool. It is never run in CI.

Start the fixture site, browser service, and FlareSolverr, then run:

```sh
make bench
```

The default run measures the `static`, `shell`, and `images` fixture routes
three times with `http`, `playwright`, and `puppeteer_stealth`. The sample
count and methods can be changed without changing the report schema:

```sh
make bench COUNT=5 METHODS=http,playwright
```

Reports are written to `build/benchmark/<git-sha>.json` and the Markdown
summary is printed to stdout. Each sample contains wall time, PHP
`getrusage()` CPU-seconds and peak RSS, plus Docker `node` and `flaresolverr`
CPU-seconds and peak RSS collected while the sample runs. p50 and p95 are
calculated per method.

The baseline for the 1.2.0-develop line must be captured before comparing a
later lane or release. Do not commit generated reports or use live URLs in
the benchmark.

