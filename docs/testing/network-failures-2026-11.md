# Live-check network failures, 2026-11-12

Follow-up of `docs/testing/live-check-results.md` (13 listing samples reported `network` from the Dev VM, Vietnam exit). Each adapter's base URL (not the failing listing URL) was requested once with the library's default browser-like User-Agent (one request per host, >= 6 s apart, no retries, no workarounds for blocks). Nothing here changes adapter code: no adapter regression was proven.

| Adapter | Base URL result (2026-11-12) | Classification | Recommended action |
|---|---|---|---|
| Aisex | 200, but 10 s to connect | Base URL reachable but slow; the 20 s live-check timeout is borderline; listing not rechecked | Recheck the listing URL; raise the timeout for this host if it flaps |
| E-BODY | 200 (age-check page, 1.4 s) | Base URL reachable; listing not rechecked | Recheck the listing URL with the live-check client |
| FC2 Content Market | 200 (3.7 s) | Base URL reachable; listing not rechecked | Recheck the listing URL with the live-check client |
| HEYZO | 403 with a Japanese maintenance page | Site-side block/maintenance for this client | Re-check later; adapter unchanged |
| Jable | 403 Cloudflare challenge | Walled (needs the browser/solver fallback) | Expected; live check is HTTP-only |
| JAV Database | connects, then times out (challenge page) | Walled | Expected; needs the solver fallback |
| JAVLibrary | 403 Cloudflare challenge | Walled | Expected; needs the solver fallback |
| kawaii* | 200 (age-check page, 2.9 s) | Base URL reachable; listing not rechecked | Recheck the listing URL with the live-check client |
| MGStage | 403 CloudFront error | Geo block (Japan only): confirmed during the MGStage adapter work (403 from this VM, pages served through a Japan VPN exit) | Use a Japan exit via `CRAWLERX_HTTP_PROXY` |
| 1Pondo | 200, but 10 s to connect | Base URL reachable but slow; listing not rechecked | Recheck the listing URL |
| Warashi | connection fails in 5 s | Host offline/unreachable (known: the source was reported offline) | Keep documented as offline |
| WPB Web Gravure | 200, 0.7 MB in 2 s (base URL only) | **Base URL reachable; listing not rechecked** (the live-run network error was transient) | Recheck the listing URL with the live-check client |
| XCITY | no connection within 20 s | Unreachable from this VM | Re-check from another network; adapter unchanged |

## Summary

- Base URL reachable now (listing request not rechecked, so not yet classified healthy): WPB, E-BODY, FC2, kawaii*, Aisex, 1Pondo (6 of 13). Next check: request each failing listing URL with the live-check client.
- Walled or blocked (needs fallback or access path, not a code fix): Jable, JAV Database, JAVLibrary, HEYZO.
- Geo: MGStage (Japan exit). Offline/unreachable: Warashi, XCITY.
- Requests spent: 1 per adapter (13 in total).
