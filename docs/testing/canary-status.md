# CrawlerX canary status

Latest full live canary: 57/68 samples passed (e37110834caf, 2026-10-25T11:46:13.669Z).
Wall time p50/p95: 915/59078 ms.
Run on the WSL workstation using the shared PHP 8.5 runtime and the project-configured Dev VM browser services. This revision includes the fetch budgets, source fallbacks, manifests and fixtures merged in PR #129 (JOOXCR-260).
Machine-readable report: [`canary-latest.json`](./canary-latest.json). The report records the tested code revision before this documentation refresh.
Avfan is not included in these 68 manifest samples and has no owner-provided cookie on this host; its authenticated pages remain unverified. AvfanProfiles is included without a cookie. This failing run is not a successful release gate.
The sample column omits query values. Repeated adapter/page-type rows are distinct manifest samples.

Failures below describe observed outcomes, not proven root causes. Shared browser-service load may affect timings, but was not measured in this run. Warashi is known offline (JOOXCR-244). Re-run `make canary SITE=<slug>` to investigate an individual source.

| Source | Failed samples | Observed attempts |
| --- | ---: | --- |
| DUGA | 2 | HTTP 503 unusable bodies, then Playwright request timeouts. |
| Jable | 3 | HTTP 403 challenges, then solver timeouts or `ERR_CONNECTION_CLOSED`; the remaining browser fallback timed out. |
| OneJav | 3 | HTTP 403 challenges; browser challenges/timeouts and solver timeouts. |
| Warashi | 3 | HTTP network errors, browser `ERR_TUNNEL_CONNECTION_FAILED`, solver `ERR_CONNECTION_REFUSED`. The runner classifies the solver error as `challenge`; this does not establish a site challenge. |

There are 57 `ok`, 2 `timeout`, 9 `challenge`, and no `parse_failed` samples. The run exits 1 as expected for these live failures. The previous run passed 58/68 samples. Manifests and source conditions changed between runs, so the totals alone do not establish a code regression. Remote service resource counters were not measured.

| Adapter | Page type | Sample | Status | Missing fields | Tracking |
| --- | --- | --- | --- | --- | --- |
| aisex | performer_listing | `/actress/` | ok | — | — |
| aisex | performer_detail | `/actress/1004850/` | ok | — | — |
| avfan_profiles | performer_listing | `/actress/cup/A.html` | ok | — | — |
| avfan_profiles | performer_detail | `/actress/%E6%B0%B8%E7%80%AC%E3%82%86%E3%81%84.html` | ok | — | — |
| avjoho | performer_listing | `/category/%e3%83%87%e3%83%93%e3%83%a5%e3%83%bc%ef%bc%882025%e5%b9%b4-%ef%bc%89/` | ok | — | — |
| avjoho | performer_detail | `/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/` | ok | — | — |
| caribbeancom | listing | `/eng/index2.htm` | ok | — | — |
| caribbeancom | detail | `/eng/moviepages/080826-001/index.html` | ok | — | — |
| duga | listing | `/ranking/` | timeout | — | JOOXCR-182 (observed; cause unconfirmed) |
| duga | detail | `/ppv/vrpandemic-0023/` | timeout | — | JOOXCR-182 (observed; cause unconfirmed) |
| eporner | gallery | `/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/` | ok | — | — |
| fc2 | listing | `/` | ok | — | — |
| fc2 | detail | `/article/4968399/` | ok | — | — |
| ffjav | listing | `/javtorrent` | ok | — | — |
| ffjav | detail | `/torrent/mida-768-%e5%a6%bb%e3%81%8c%e5%b8%b0%e7%9c%81%e4%b8%ad%e3%80%812%e4%ba%ba%e3%81%8d%e3%82%8a%e3%81%ae%e8%87%aa%e5%ae%85%e3%81%a7%e4%bd%95%e3%81%a7%e3%82%82ok%e3%81%97%e3%81%a6%e3%81%8f%e3%82%8c` | ok | — | — |
| heyzo | listing | `/listpages/all_1.html` | ok | — | — |
| heyzo | detail | `/moviepages/3927/index.html` | ok | — | — |
| jable | listing | `/new-release/` | ok | — | — |
| jable | detail | `/videos/fjin-091/` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| jable | detail | `/videos/jur-832/` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| jable | performer_listing | `/models/` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| jable | performer_detail | `/models/bfaca44240620be2f3092c294fb22fbe/` | ok | — | — |
| javbus | listing | `/en/` | ok | — | — |
| javbus | detail | `/en/NAMH-074` | ok | — | — |
| javbus | performer_listing | `/en/actresses` | ok | — | — |
| javbus | performer_detail | `/en/star/11p7` | ok | — | — |
| javbus | performer_detail | `/en/star/okq` | ok | — | — |
| javdatabase | performer_listing | `/idols/` | ok | — | — |
| javdatabase | performer_detail | `/idols/yua-mikami/` | ok | — | — |
| javdb | listing | `/` | ok | — | — |
| javdb | detail | `/v/a8yV0p` | ok | — | — |
| javlibrary | listing | `/en/vl_newrelease.php` | ok | — | — |
| javlibrary | detail | `/en/javme3rasy.html` | ok | — | — |
| javlibrary | detail | `/en/javme3tuqi.html` | ok | — | — |
| javlibrary | performer_listing | `/en/star_list.php?prefix` | ok | — | — |
| javlibrary | performer_detail | `/en/vl_star.php?s` | ok | — | — |
| javphotos | listing | `/free/` | ok | — | — |
| javphotos | gallery | `/free/1pondo-hana-aoyama-sexist-mobile-pics` | ok | — | — |
| javbtc | listing | `/` | ok | — | — |
| javbtc | detail | `/r18/s1no1style-yua-mikami-sougouwiki-celebrity` | ok | — | — |
| minnanoav | performer_listing | `/actress_list.php` | ok | — | — |
| minnanoav | performer_detail | `/actress945093.html` | ok | — | — |
| minnanoav | listing | `/actress.php?actress_id` | ok | — | — |
| minnanoav | detail | `/av159081.html` | ok | — | — |
| missav | listing | `/en/new` | ok | — | — |
| missav | detail | `/en/fns-247` | ok | — | — |
| 141jav | listing | `/new?page` | ok | — | — |
| 141jav | detail | `/torrent/JUQ521` | ok | — | — |
| onepondo | listing | `/dyn/phpauto/movie_lists/list_newest_0.json` | ok | — | — |
| onepondo | detail | `/dyn/phpauto/movie_details/movie_id/060426_001.json` | ok | — | — |
| onejav | listing | `/new` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| onejav | detail | `/torrent/ymds282` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| onejav | listing | `/tag/FC2` | challenge | — | JOOXCR-182 (observed; cause unconfirmed) |
| tokyohot | listing | `/index` | ok | — | — |
| tokyohot | listing | `/product/` | ok | — | — |
| tokyohot | detail | `/product/n1700/` | ok | — | — |
| tokyohot | detail | `/product/adl_301/` | ok | — | — |
| warashi | gallery | `/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092` | challenge | — | JOOXCR-244 |
| warashi | performer_listing | `/en/s-2-2/female-pornstars/toutes/all/page/1` | challenge | — | JOOXCR-244 |
| warashi | performer_detail | `/en/s-2-0/yua-mikami/asian-female-pornstar/1234` | challenge | — | JOOXCR-244 |
| xcity | listing | `/avod/list/` | ok | — | — |
| xcity | detail | `/avod/detail/?id` | ok | — | — |
| xcity | detail | `/avod/detail/?id` | ok | — | — |
| xcity | performer_detail | `/idol/detail/10696/` | ok | — | — |
| xcity | performer_listing | `/idol/` | ok | — | — |
| xcity | performer_listing | `/idol/?kana` | ok | — | — |
| xcity | performer_listing | `/idol/?ini&num` | ok | — | — |
| xcity | performer_detail | `/idol/detail/5517/` | ok | — | — |
