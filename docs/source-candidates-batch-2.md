# Source candidates, batch 2 (2026-11-12)

**Scope:** public Japanese AV/gravure maker sites, discovered through the maker and label categories of ja.wikipedia (157 pages, 273 external hosts not in the 89 adapter manifests or in batch 1). 70 plausible maker hosts were checked with `robots.txt` and the home page (at most 3 requests per host, >= 5 s apart, default browser-like User-Agent, no workaround of any block). 52 of them did not answer or did not allow reading (timeout, connection failure, or robots without a usable policy); they are not listed.

This document records discovery evidence only; it adds no adapters. **Honest limit:** only 4 hosts reached a verified list-to-detail path with an inventory; the remaining entries are classified by what the retained responses showed. Rights and terms are not reviewed.

## Summary

| Recommendation | Count |
|---|---|
| Build | 4 |
| Needs verification / access analysis | 5 |
| Skip | 5 |

## Build candidates (list reachable, detail page checked)

| Candidate | Host | Detail path | Detail evidence | Recommendation |
|---|---|---|---|---|
| YUIRA | `yuira.jp` | `/product/<slug>` | 26 images on the detail page (package and stills); labelled rows were not exposed as dt/th, so field extraction needs a dedicated parser; robots 200 | Build after the exact field markup is inspected |
| COMMA VISION | `comma.co.jp` | `/dvd/<code>/` | 7 images, product code in the URL (comt-070b); robots 200 | Build (small catalog) |
| TMA (Total Media Agency) | `www.tma.co.jp` | `/products/<code>` | 18 images (t38-073); robots 200 | Build |
| DIGITAL ARK | `digital-ark.com` | age-check page links to `shop.digital-ark.com/index.html`, items at `/view/item/<id>` | 18 images, 1 Open Graph image; title and artwork on the item page, labelled rows not exposed as dt/th; robots 200 | Build after confirming terms for the shop subdomain |

## Needs verification or access analysis, and skipped

| Candidate | Host | What was seen | Recommendation |
|---|---|---|---|
| SNIPER (Taiyo Tosho SM portal) | `sniper.jp` | age-check page; `/dvd/index.html` is a catalog index with many images, not a single work page | Needs verification: find the per-work URL pattern |
| Aurora Project | `aurora-pro.com` | age-check page whose click-through link is `/-/index/p/answer_yes=1/`; the page after it exposed no work links in the first response | Needs access analysis |
| Takara Visual | `takara-tv.jp` | age gate then work links go to FANZA/DMM (external) | Skip (external catalog) |
| JADE / SLUM PEEPING | `p-jade.com / p-slum.com` | age-check page; work pages not found from the gate or home | Needs access analysis |
| Dogma | `dogma.co.jp` | age-check page; click-through link not identified in the first response | Needs access analysis |
| kmp channel | `kmpch.jp` | age-check page; CS channel site, not a work catalog | Skip |
| HUNTER | `hunter-pp.jp` | `robots.txt` answers 301 (policy unknown), home 200 without links to works | Skip (policy unknown) |
| BALTAN | `baltan-av.com` | age-check page only; no work link found | Needs access analysis |
| MATCHLOCK | `matchlock.co.jp` | corporate page (no catalog found) | Skip |
| VRonWEBMED / L.A studio / ALL Create | `vron.jp, la-studio.jp, allcreate.jp` | not work catalogs (news, studio rental, cleaning) | Skip |

## Not reached

52 of the 70 checked hosts timed out, refused the connection, redirected without a usable `robots.txt`, or served pages that are not a work catalog; no further requests were made to them.

## Follow-up

1. Start with COMMA VISION, TMA and YUIRA (plain HTTP, no age gate); capture a list page 1, page 2 and one detail for each before writing the adapter.
2. Age-gated makers (Aurora Project, Dogma, BALTAN, SNIPER) need the click-through route found from the gate page itself, as for Natural High (cookie) and Fair&Way (`/top`).
