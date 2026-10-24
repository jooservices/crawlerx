# CrawlerX canary status

Latest full live canary: 58/68 samples passed (74ff40d8737d, 2026-10-24T14:32:35.476Z).
Wall time p50/p95: 2216/105089 ms. Methods: http 48, playwright 3, flaresolverr 8.
Run on the WSL staging host against the Dev VM browser services (FlareSolverr and Playwright) while the JavMeta crawl was running at the same time on the same services. Avfan was not included (it needs the owner-provided cookie, which is not on this host); AvfanProfiles was checked without a cookie.
Machine-readable report: [`canary-latest.json`](./canary-latest.json) (the stable copy of the latest full run; schema in `tools/canary/run.mjs`).
The sample column shows the path and query-key names with query values omitted; repeated adapter/page-type rows are distinct manifest samples.

Reading the failures: the timeouts and challenges are not parser failures. They happen while the crawl keeps the shared browser services busy (JOOXCR-252 caps that load in JavMeta) and for warashi, whose site is offline (JOOXCR-244). Re-run `make canary SITE=<slug>` for a single site when the crawl is quiet before opening a bug.

| Adapter | Page type | Sample | Status | Missing fields | XP item |
| --- | --- | --- | --- | --- | --- |
| 141jav | detail | `/torrent/JUQ521` | ok | — | — |
| 141jav | listing | `/new?page` | ok | — | — |
| aisex | performer_detail | `/actress/1004850/` | ok | — | — |
| aisex | performer_listing | `/actress/` | ok | — | — |
| avfan_profiles | performer_detail | `/actress/%E6%9C%89%E6%A0%96%E8%88%9E%E8%A1%A3.html` | timeout | — | JOOXCR-252 |
| avfan_profiles | performer_listing | `/actress/cup/A.html` | ok | — | — |
| avjoho | performer_detail | `/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/` | ok | — | — |
| avjoho | performer_listing | `/category/%e3%83%87%e3%83%93%e3%83%a5%e3%83%bc%ef%bc%882025%e5%b9%b4-%ef%bc%89/` | ok | — | — |
| caribbeancom | detail | `/eng/moviepages/080826-001/index.html` | ok | — | — |
| caribbeancom | listing | `/eng/index2.htm` | ok | — | — |
| duga | detail | `/ppv/vrpandemic-0023/` | ok | — | — |
| duga | listing | `/ranking/` | timeout | — | JOOXCR-252 |
| eporner | gallery | `/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/` | ok | — | — |
| fc2 | detail | `/article/4968399/` | ok | — | — |
| fc2 | listing | `/` | ok | — | — |
| ffjav | detail | `/torrent/mida-768-%e5%a6%bb%e3%81%8c%e5%b8%b0%e7%9c%81%e4%b8%ad%e3%80%812%e4%ba%ba%e3%81%8d%e3%82%8a%e3%81%ae%e8%87%aa%e5%ae%85%e3%81%a7%e4%bd%95%e3%81%a7%e3%82%82ok%e3%81%97%e3%81%a6%e3%81%8f%e3%82%8c` | ok | — | — |
| ffjav | listing | `/javtorrent` | ok | — | — |
| heyzo | detail | `/moviepages/3927/index.html` | ok | — | — |
| heyzo | listing | `/listpages/all_1.html` | ok | — | — |
| jable | detail | `/videos/fjin-091/` | ok | — | — |
| jable | detail | `/videos/jur-832/` | timeout | — | JOOXCR-252 |
| jable | listing | `/new-release/` | ok | — | — |
| jable | performer_detail | `/models/bfaca44240620be2f3092c294fb22fbe/` | ok | — | — |
| jable | performer_listing | `/models/` | ok | — | — |
| javbtc | detail | `/r18/s1no1style-yua-mikami-sougouwiki-celebrity` | ok | — | — |
| javbtc | listing | `/` | ok | — | — |
| javbus | detail | `/en/NAMH-074` | ok | — | — |
| javbus | listing | `/en/` | ok | — | — |
| javbus | performer_detail | `/en/star/11p7` | ok | — | — |
| javbus | performer_detail | `/en/star/okq` | ok | — | — |
| javbus | performer_listing | `/en/actresses` | ok | — | — |
| javdatabase | performer_detail | `/idols/yua-mikami/` | ok | — | — |
| javdatabase | performer_listing | `/idols/` | ok | — | — |
| javdb | detail | `/v/a8yV0p` | ok | — | — |
| javdb | listing | `/` | ok | — | — |
| javlibrary | detail | `/en/javme3rasy.html` | challenge | — | JOOXCR-252 |
| javlibrary | detail | `/en/javme3tuqi.html` | challenge | — | JOOXCR-252 |
| javlibrary | listing | `/en/vl_newrelease.php` | ok | — | — |
| javlibrary | performer_detail | `/en/vl_star.php?s` | challenge | — | JOOXCR-252 |
| javlibrary | performer_listing | `/en/star_list.php?prefix` | challenge | — | JOOXCR-252 |
| javphotos | gallery | `/free/1pondo-hana-aoyama-sexist-mobile-pics` | ok | — | — |
| javphotos | listing | `/free/` | ok | — | — |
| minnanoav | detail | `/av159081.html` | ok | — | — |
| minnanoav | listing | `/actress.php?actress_id` | ok | — | — |
| minnanoav | performer_detail | `/actress945093.html` | ok | — | — |
| minnanoav | performer_listing | `/actress_list.php` | ok | — | — |
| missav | detail | `/en/fns-247` | ok | — | — |
| missav | listing | `/en/new` | ok | — | — |
| onejav | detail | `/torrent/ymds282` | ok | — | — |
| onejav | listing | `/new` | ok | — | — |
| onejav | listing | `/tag/FC2` | ok | — | — |
| onepondo | detail | `/dyn/phpauto/movie_details/movie_id/060426_001.json` | ok | — | — |
| onepondo | listing | `/dyn/phpauto/movie_lists/list_newest_0.json` | ok | — | — |
| tokyohot | detail | `/product/adl_301/` | ok | — | — |
| tokyohot | detail | `/product/n1700/` | ok | — | — |
| tokyohot | listing | `/product/` | ok | — | — |
| tokyohot | listing | `/index` | ok | — | — |
| warashi | gallery | `/en/s-7-0/yuna-ishikawa/female-asian-pornstar/photo-gallery/56092` | timeout | — | JOOXCR-244 |
| warashi | performer_detail | `/en/s-2-0/yua-mikami/asian-female-pornstar/1234` | timeout | — | JOOXCR-244 |
| warashi | performer_listing | `/en/s-2-2/female-pornstars/toutes/all/page/1` | timeout | — | JOOXCR-244 |
| xcity | detail | `/avod/detail/?id` | ok | — | — |
| xcity | detail | `/avod/detail/?id` | ok | — | — |
| xcity | listing | `/avod/list/` | ok | — | — |
| xcity | performer_detail | `/idol/detail/10696/` | ok | — | — |
| xcity | performer_detail | `/idol/detail/5517/` | ok | — | — |
| xcity | performer_listing | `/idol/` | ok | — | — |
| xcity | performer_listing | `/idol/?kana` | ok | — | — |
| xcity | performer_listing | `/idol/?ini&num` | ok | — | — |
