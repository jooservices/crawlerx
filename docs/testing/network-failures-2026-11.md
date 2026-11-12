# Live-check network failures, 2026-11-12

Follow-up of `docs/testing/live-check-results.md` (13 listing samples reported `network` from the Dev VM, Vietnam exit). Each adapter's base URL was requested once with the library's default browser-like User-Agent (one request per host, >= 6 s apart, no retries, no workarounds for blocks). Nothing here changes adapter code: no adapter regression was proven.

| Adapter | Base URL result (2026-11-12) | Classification | Recommended action |
|---|---|---|---|
| Aisex | 200, but 10 s to connect | Slow host; the 20 s live-check timeout is borderline | None; raise the live-check timeout for this host if it flaps |
| E-BODY | 200 (age-check page, 1.4 s) | Transient in the live run | None |
| FC2 Content Market | 200 (3.7 s) | Transient in the live run | None |
| HEYZO | 403 with a Japanese maintenance page | Site-side block/maintenance for this client | Re-check later; adapter unchanged |
| Jable | 403 Cloudflare challenge | Walled (needs the browser/solver fallback) | Expected; live check is HTTP-only |
| JAV Database | connects, then times out (challenge page) | Walled | Expected; needs the solver fallback |
| JAVLibrary | 403 Cloudflare challenge | Walled | Expected; needs the solver fallback |
| kawaii* | 200 (age-check page, 2.9 s) | Transient in the live run | None |
| MGStage | 403 CloudFront error | Geo block (Japan only) | Use a Japan exit via `CRAWLERX_HTTP_PROXY` |
| 1Pondo | 200, but 10 s to connect | Slow host; borderline timeout | None |
| Warashi | connection fails in 5 s | Host offline/unreachable (known: the source was reported offline) | Keep documented as offline |
| WPB Web Gravure | 200, 0.7 MB in 2 s | **Healthy: no regression** (the live-run network error was transient) | None |
| XCITY | no connection within 20 s | Unreachable from this VM | Re-check from another network; adapter unchanged |

## Summary

- Healthy now: WPB, E-BODY, FC2, kawaii*, Aisex, 1Pondo (6 of 13); the live-run failures were transient or slow hosts.
- Walled or blocked (needs fallback or access path, not a code fix): Jable, JAV Database, JAVLibrary, HEYZO.
- Geo: MGStage (Japan exit). Offline/unreachable: Warashi, XCITY.
- Requests spent: 1 per adapter (13 in total).
