# Live adapter health report

Run on 2026-11-11/12 from the Dev VM (no proxy) with `composer live-check:report` (JOOXCR-502). The tool checks one listing and one detail sample per adapter, at most two outbound requests per adapter (robots lookup included) and >= 10 s between requests to one host.

## Summary

- Adapters checked: 88; samples: 175.
- Listing samples: **ok 67**, blocked 2, network 13 (the rest not checked).
- **Detail samples were not checked for most adapters (93 not-checked, reason `request_limit`)**: the two-request budget is spent on the robots lookup and the listing, so a detail check rarely fits. Treat this report as a listing-level health check only; widening the budget or checking details first is a tool follow-up.

## Needs attention (15 samples)

| Adapter | Type | Status | URL |
| --- | --- | --- | --- |
| Aisex (`aisex`) | performer_listing | network (network) | https://www.aisex.jp/actress/ |
| E-BODY (`ebody`) | listing | network (network) | https://av-e-body.com/works/list/date/2026-11-17 |
| FC2 Content Market (`fc2`) | listing | network (network) | https://adult.contents.fc2.com/ |
| HEYZO (`heyzo`) | listing | network (network) | https://en.heyzo.com/listpages/all_1.html |
| Jable (`jable`) | listing | network (network) | https://en.jable.tv/new-release/ |
| JavBus (`javbus`) | listing | blocked (blocked) | https://www.javbus.com/en/ |
| JavBus (`javbus`) | detail | blocked (blocked) | https://www.javbus.com/en/NAMH-074 |
| JAV Database (`javdatabase`) | performer_listing | network (network) | https://www.javdatabase.com/idols/ |
| JAVLibrary (`javlibrary`) | listing | network (network) | https://www.javlibrary.com/en/vl_newrelease.php |
| kawaii* (`kawaiikawaii`) | listing | network (network) | https://kawaiikawaii.jp/works/date |
| MGStage (`mgstage`) | listing | network (network) | https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top |
| 1Pondo (`onepondo`) | listing | network (network) | https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json |
| Warashi (`warashi`) | performer_listing | network (network) | https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1 |
| WPB Web Gravure (`wpb`) | listing | network (network) | https://wpb.shueisha.co.jp/gravure/list/ |
| XCITY (`xcity`) | listing | network (network) | https://xxx.xcity.jp/avod/list/ |

## Full report

- Generated: 2026-11-11T21:44:22+00:00
- Adapters checked: 88
- Samples checked: 175
- Results: ok 67, empty 0, blocked 2, parse-error 0, network 13, not-checked 93

| Adapter | Sample | Type | Status | Items | Duration | URL |
| --- | --- | --- | --- | ---: | ---: | --- |
| Aisex (`aisex`) | listing (`listing-page1.html`) | performer_listing | network (network) | 0 | 20.27s | https://www.aisex.jp/actress/ |
| Aisex (`aisex`) | detail (`detail-aiuti-siori.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://www.aisex.jp/actress/1004850/ |
| Asagei Plus (`asagei`) | listing (`listing-gravure-page1.html`) | listing | ok | 14 | 10.61s | https://www.asagei.com/tag/%E3%82%B0%E3%83%A9%E3%83%93%E3%82%A2 |
| Asagei Plus (`asagei`) | detail (`gallery-349499.html`) | gallery | not-checked (request_limit) | 0 | 0.49s | https://www.asagei.com/excerpt/349499 |
| ATTACKERS (`attackers`) | listing (`list-2026-12-01.html`) | listing | ok | 22 | 11.42s | https://attackers.net/works/list/date/2026-12-01 |
| ATTACKERS (`attackers`) | detail (`detail-ADN818.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://attackers.net/works/detail/ADN818 |
| AV-Wiki (`avwiki`) | listing (`listing-page1.json`) | listing | ok | 2 | 11.19s | https://av-wiki.net/wp-json/wp/v2/posts?per_page=2&page=1&_embed=1 |
| AV-Wiki (`avwiki`) | detail (`detail-200gana-3482.html`) | detail | not-checked (request_limit) | 0 | 1.22s | https://av-wiki.net/200gana-3482/ |
| AV-Actress (`avactress`) | listing (`performers-popular.html`) | performer_listing | ok | 100 | 11.57s | https://av-actress.com/popular |
| AV-Actress (`avactress`) | detail (`performer-1044864.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://av-actress.com/1044864/%E6%B2%B3%E5%8C%97%E5%BD%A9%E8%8A%B1%EF%BC%88%E6%B2%B3%E5%8C%97%E5%BD%A9%E4%BC%8A%EF%BC%89 |
| Avfan (`avfan`) | listing (`listing-placeholder.html`) | listing | ok | 54 | 11.02s | https://avfan.com/en |
| Avfan (`avfan`) | detail (`detail-ewTlM3Fj.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://avfan.com/en/movies/ewTlM3Fj |
| Avfan Profiles (`avfan_profiles`) | listing (`listing-cup-a.html`) | performer_listing | ok | 104 | 10.64s | https://av-fan.tokyo/actress/cup/A.html |
| Avfan Profiles (`avfan_profiles`) | detail (`detail-current.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://av-fan.tokyo/actress/%E6%B0%B8%E7%80%AC%E3%82%86%E3%81%84.html |
| Avjoho (`avjoho`) | listing (`listing-page1.html`) | performer_listing | ok | 20 | 10.95s | https://db.avjoho.com/category/%e3%83%87%e3%83%93%e3%83%a5%e3%83%bc%ef%bc%882025%e5%b9%b4-%ef%bc%89/ |
| Avjoho (`avjoho`) | detail (`detail-ichise-airi.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://db.avjoho.com/%e5%b8%82%e7%80%ac%e3%81%82%e3%81%84%e3%82%8a/ |
| BeFreeBe (`befreebe`) | listing (`list-2026-12-01.html`) | listing | ok | 2 | 10.97s | https://befreebe.com/works/list/date/2026-12-01 |
| BeFreeBe (`befreebe`) | detail (`detail-BF775.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://befreebe.com/works/detail/BF775 |
| BI (`bi`) | listing (`list-2026-11-24.html`) | listing | ok | 7 | 11.21s | https://bi-av.com/works/list/date/2026-11-24 |
| BI (`bi`) | detail (`detail-CJOD549.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://bi-av.com/works/detail/CJOD549 |
| BIBIAN (`bibian`) | listing (`list-2026-11-10.html`) | listing | ok | 5 | 11.62s | https://bibian-av.com/works/list/date/2026-11-10 |
| BIBIAN (`bibian`) | detail (`detail-BBAN612.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://bibian-av.com/works/detail/BBAN612 |
| BOMB.tv (`bomb`) | listing (`index.html`) | listing | ok | 108 | 11.79s | https://www.bomb.tv/plus/index.html |
| BOMB.tv (`bomb`) | detail (`idol-mita.html`) | gallery | not-checked (request_limit) | 0 | 8.28s | https://www.bomb.tv/plus/idolindex/mita.html |
| Caribbeancom (`caribbeancom`) | listing (`listing.html`) | listing | ok | 20 | 10.94s | https://en.caribbeancom.com/eng/index2.htm |
| Caribbeancom (`caribbeancom`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 0.12s | https://en.caribbeancom.com/eng/moviepages/080826-001/index.html |
| Caribbeancom Premium (`caribbeancompr`) | listing (`listing.html`) | listing | ok | 40 | 11.24s | https://en.caribbeancompr.com/eng/listpages/1.html |
| Caribbeancom Premium (`caribbeancompr`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 0.32s | https://en.caribbeancompr.com/eng/moviepages/060126_001/index.html |
| Wikimedia Commons (`commons`) | listing (`listing-root.json`) | listing | not-checked (request_limit) | 0 | 10.64s | https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&list=categorymembers&cmtitle=Category%3AGravure+idols+from+Japan&cmtype=subcat%7Cfile&cmlimit=5&cmprop=ids%7Ctitle%7Ctype%7Ctimestamp%7Csortkeyprefix |
| Wikimedia Commons (`commons`) | detail (`performer-yuzuki-aikawa.json`) | performer_detail | not-checked (request_limit) | 0 | 0.38s | https://commons.wikimedia.org/w/api.php?action=query&format=json&formatversion=2&titles=Category%3AYuzuki+Aikawa&prop=categoryinfo%7Cpageprops%7Cpageimages%7Cinfo%7Crevisions%7Ccategories%7Clanglinks%7Cextlinks&inprop=url&piprop=original%7Cthumbnail%7Cname&pithumbsize=320&rvprop=content%7Ctimestamp&rvslots=main&cllimit=max&lllimit=max&ellimit=max |
| DAHLIA (`dahlia`) | listing (`list-work.html`) | listing | ok | 20 | 10.94s | https://dahlia-av.jp/work/ |
| DAHLIA (`dahlia`) | detail (`detail-DLDSS-573.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://dahlia-av.jp/works/dldss573/ |
| DANDY GROUP (`dandy`) | listing (`label-dandy.html`) | listing | ok | 10 | 11.38s | https://www.choi-waru.com/dandy/ |
| DANDY GROUP (`dandy`) | detail (`detail-dandy-880.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.choi-waru.com/dandy/dandy-880/ |
| DASDAS (`dasdas`) | listing (`list-2026-11-24.html`) | listing | ok | 13 | 10.86s | https://dasdas.jp/works/list/date/2026-11-24 |
| DASDAS (`dasdas`) | detail (`detail-DAZD317.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://dasdas.jp/works/detail/DAZD317 |
| DEEP'S (`deeps`) | listing (`list.html`) | listing | ok | 35 | 10.86s | https://deeps.net/item/ |
| DEEP'S (`deeps`) | detail (`detail-mmgo-020.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://deeps.net/item/detail.php?mmgo-020 |
| DUGA (`duga`) | listing (`listing-ranking.html`) | listing | not-checked (request_limit) | 0 | 11.61s | https://duga.jp/ranking/ |
| DUGA (`duga`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 10.00s | https://duga.jp/ppv/vrpandemic-0023/ |
| E-BODY (`ebody`) | listing (`list-2026-11-17.html`) | listing | network (network) | 0 | 20.11s | https://av-e-body.com/works/list/date/2026-11-17 |
| E-BODY (`ebody`) | detail (`detail-EBWH392.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://av-e-body.com/works/detail/EBWH392 |
| EPORNER (`eporner`) | detail (`gallery-sample.html`) | gallery | ok | 1 | 11.10s | https://www.eporner.com/gallery/xKeoFe7VHmO/Iori-Kogawa-gu-chuaniori-STAR-836-Uncensored-Leak/ |
| FAIR＆WAY (`fairway`) | listing (`list-release.html`) | listing | ok | 4 | 10.57s | https://fairway-av.com/works/list/release |
| FAIR＆WAY (`fairway`) | detail (`detail-FWAY088.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://fairway-av.com/works/detail/FWAY088?page_from=genre&sys_code=214 |
| FALENO GROUP (`falenogroup`) | listing (`list-work.html`) | listing | ok | 28 | 10.68s | https://falenogroup.com/work/ |
| FALENO GROUP (`falenogroup`) | detail (`detail-fthtd-243.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://falenogroup.com/works/fthtd-243/ |
| FC2 Content Market (`fc2`) | listing (`listing.html`) | listing | network (network) | 0 | 25.12s | https://adult.contents.fc2.com/ |
| FC2 Content Market (`fc2`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://adult.contents.fc2.com/article/4968399/ |
| FFJav (`ffjav`) | listing (`listing-page-1.html`) | listing | ok | 10 | 10.99s | https://ffjav.com/javtorrent |
| FFJav (`ffjav`) | detail (`detail-sample-1.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://ffjav.com/torrent/mida-768-%e5%a6%bb%e3%81%8c%e5%b8%b0%e7%9c%81%e4%b8%ad%e3%80%812%e4%ba%ba%e3%81%8d%e3%82%8a%e3%81%ae%e8%87%aa%e5%ae%85%e3%81%a7%e4%bd%95%e3%81%a7%e3%82%82ok%e3%81%97%e3%81%a6%e3%81%8f%e3%82%8c |
| Fitch (`fitch`) | listing (`list-2026-12-01.html`) | listing | ok | 6 | 10.72s | https://fitch-av.com/works/list/date/2026-12-01 |
| Fitch (`fitch`) | detail (`detail-DEAB010.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://fitch-av.com/works/detail/DEAB010 |
| Gentosha (`gentosha`) | listing (`listing-page1.html`) | listing | ok | 20 | 10.64s | https://www.gentosha.co.jp/book/new/ |
| Gentosha (`gentosha`) | detail (`detail-9784344046368.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.gentosha.co.jp/book/detail/9784344046368/ |
| Gravidia (`gravidia`) | listing (`listing-page-1.html`) | listing | ok | 16 | 10.66s | https://gravidia.jp/search/?post_type=product&cts=cts&s= |
| Gravidia (`gravidia`) | detail (`detail-48277.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://gravidia.jp/product/48277 |
| Gravure-ch (`gravurech`) | listing (`listing-new-photo.html`) | listing | ok | 60 | 11.90s | https://gravure-ch.com/search/new/photo |
| Gravure-ch (`gravurech`) | detail (`detail-j6R.html`) | gallery | not-checked (request_limit) | 0 | 0.03s | https://gravure-ch.com/content/j6R |
| Hajime Kikaku (`hajimekikaku`) | listing (`list-2026-11-17.html`) | listing | ok | 5 | 11.42s | https://hajimekikaku.com/works/list/date/2026-11-17 |
| Hajime Kikaku (`hajimekikaku`) | detail (`detail-HJMO745.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://hajimekikaku.com/works/detail/HJMO745 |
| HEYZO (`heyzo`) | listing (`listing.html`) | listing | network (network) | 0 | 10.22s | https://en.heyzo.com/listpages/all_1.html |
| HEYZO (`heyzo`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 1.50s | https://en.heyzo.com/moviepages/3927/index.html |
| HHH (`hhh`) | listing (`list-2026-11-24.html`) | listing | ok | 17 | 11.88s | https://hhh-av.com/works/list/date/2026-11-24 |
| HHH (`hhh`) | detail (`detail-HUNTC688.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://hhh-av.com/works/detail/HUNTC688 |
| Hiyoko (`hiyoko`) | listing (`listing-page1.html`) | listing | ok | 30 | 10.49s | https://www.hiyoko-av.com/title/ |
| Hiyoko (`hiyoko`) | detail (`detail-piyo-242.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.hiyoko-av.com/title/piyo-242/ |
| HONNAKA (`honnaka`) | listing (`list-2026-11-24.html`) | listing | ok | 6 | 13.89s | https://honnaka.jp/works/list/date/2026-11-24 |
| HONNAKA (`honnaka`) | detail (`detail-HNMG003.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://honnaka.jp/works/detail/HNMG003 |
| IDEAPOCKET (`ideapocket`) | listing (`list-2026-12-01.html`) | listing | ok | 12 | 12.63s | https://ideapocket.com/works/list/date/2026-12-01 |
| IDEAPOCKET (`ideapocket`) | detail (`detail-IDPC034.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://ideapocket.com/works/detail/IDPC034 |
| Idol Gakuen (`idolgakuen`) | listing (`listing-page1.html`) | listing | ok | 25 | 11.16s | https://idol-gakuen.jp/item-list/ |
| Idol Gakuen (`idolgakuen`) | detail (`detail-117040.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://idol-gakuen.jp/item/117040/ |
| Indies (`indies`) | listing (`list-202611.html`) | listing | ok | 11 | 10.64s | https://www.indies-av.co.jp/ym/202611/ |
| Indies (`indies`) | detail (`detail-dsdp049.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.indies-av.co.jp/title/dsdp049/ |
| Jable (`jable`) | listing (`listing-new-release.html`) | listing | network (network) | 0 | 15.01s | https://en.jable.tv/new-release/ |
| Jable (`jable`) | detail (`detail-fjin-091.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://en.jable.tv/videos/fjin-091/ |
| Jav321 (`jav321`) | listing (`listing-genre-1018.html`) | listing | ok | 48 | 11.09s | https://www.jav321.com/genre/1018/1 |
| Jav321 (`jav321`) | detail (`detail-118rtp00020.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.jav321.com/video/118rtp00020 |
| JavBus (`javbus`) | listing (`listing-live.html`) | listing | blocked (blocked) | 0 | 0.99s | https://www.javbus.com/en/ |
| JavBus (`javbus`) | detail (`detail-namh-074-live.html`) | detail | blocked (blocked) | 0 | 0.00s | https://www.javbus.com/en/NAMH-074 |
| JAV Database (`javdatabase`) | listing (`listing_page1.html`) | performer_listing | network (network) | 0 | 15.01s | https://www.javdatabase.com/idols/ |
| JAV Database (`javdatabase`) | detail (`detail_yua_mikami.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://www.javdatabase.com/idols/yua-mikami/ |
| JavDB (`javdb`) | listing (`listing.html`) | listing | ok | 40 | 10.59s | https://javdb.com/ |
| JavDB (`javdb`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 9.55s | https://javdb.com/v/a8yV0p |
| JAVLibrary (`javlibrary`) | listing (`listing-live.html`) | listing | network (network) | 0 | 15.05s | https://www.javlibrary.com/en/vl_newrelease.php |
| JAVLibrary (`javlibrary`) | detail (`detail-javme3rasy-live.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.javlibrary.com/en/javme3rasy.html |
| JAVMenu (`javmenu`) | listing (`list-censored-page2.html`) | listing | ok | 23 | 10.28s | https://javmenu.com/censored?page=2 |
| JAVMenu (`javmenu`) | detail (`detail-ABF-386.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://javmenu.com/ABF-386 |
| JavPhotos (`javphotos`) | listing (`listing-page1.html`) | listing | ok | 60 | 10.56s | https://jav.photos/free/ |
| JavPhotos (`javphotos`) | detail (`gallery-hana-aoyama.html`) | gallery | not-checked (request_limit) | 0 | 0.00s | https://jav.photos/free/1pondo-hana-aoyama-sexist-mobile-pics |
| JavBTC (`javbtc`) | listing (`listing-page-1.html`) | listing | ok | 48 | 10.45s | https://javbtc.com/ |
| JavBTC (`javbtc`) | detail (`detail-sample-1.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://javbtc.com/r18/s1no1style-yua-mikami-sougouwiki-celebrity |
| kawaii* (`kawaiikawaii`) | listing (`dates.html`) | listing | network (network) | 0 | 20.22s | https://kawaiikawaii.jp/works/date |
| kawaii* (`kawaiikawaii`) | detail (`detail-KWBD444.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://kawaiikawaii.jp/works/detail/KWBD444 |
| kira*kira (`kirakira`) | listing (`list-2026-11-17.html`) | listing | ok | 2 | 10.87s | https://kirakira-av.com/works/list/date/2026-11-17 |
| kira*kira (`kirakira`) | detail (`detail-KIBD359.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://kirakira-av.com/works/detail/KIBD359 |
| Madonna (`madonna`) | listing (`list-2026-11-10.html`) | listing | ok | 30 | 11.73s | https://madonna-av.com/works/list/date/2026-11-10 |
| Madonna (`madonna`) | detail (`detail-JUR920.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://madonna-av.com/works/detail/JUR920 |
| Materiall (`materiall`) | listing (`list-work.html`) | listing | ok | 12 | 10.75s | https://materiall.jp/work/ |
| Materiall (`materiall`) | detail (`detail-mtall-149.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://materiall.jp/works/mtall-149/ |
| MGStage (`mgstage`) | listing (`listing-new.html`) | listing | network (network) | 0 | 10.27s | https://www.mgstage.com/search/cSearch.php?search_word=&sort=new&list_cnt=30&type=top |
| MGStage (`mgstage`) | detail (`detail-259luxu-1000.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.mgstage.com/product/product_detail/259LUXU-1000/ |
| MIMAN (`miman`) | listing (`list-2024-01-02.html`) | listing | ok | 1 | 10.74s | https://miman.jp/works/list/date/2024-01-02 |
| MIMAN (`miman`) | detail (`detail-MMXD035.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://miman.jp/works/detail/MMXD035 |
| Minnano AV (`minnanoav`) | listing (`movie-listing.html`) | listing | ok | 19 | 10.92s | https://www.minnano-av.com/actress.php?actress_id=945093 |
| Minnano AV (`minnanoav`) | detail (`movie-detail.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.minnano-av.com/av159081.html |
| MissAV (`missav`) | listing (`listing-new.html`) | listing | not-checked (request_limit) | 0 | 10.89s | https://missav.ws/en/new |
| MissAV (`missav`) | detail (`detail-fns-247.html`) | detail | not-checked (request_limit) | 0 | 5.00s | https://missav.ws/en/fns-247 |
| MKO LABO (`mko`) | listing (`list-2026-11-24.html`) | listing | ok | 5 | 11.48s | https://mko-labo.net/works/list/date/2026-11-24 |
| MKO LABO (`mko`) | detail (`detail-MISM459.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://mko-labo.net/works/detail/MISM459 |
| MOODYZ (`moodyz`) | listing (`list-2026-11-03.html`) | listing | ok | 26 | 11.52s | https://moodyz.com/works/list/date/2026-11-03 |
| MOODYZ (`moodyz`) | detail (`detail-MIDA834.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://moodyz.com/works/detail/MIDA834 |
| MUKU (`muku`) | listing (`list-2026-11-17.html`) | listing | ok | 14 | 11.14s | https://muku.tv/works/list/date/2026-11-17 |
| MUKU (`muku`) | detail (`detail-MUDR408.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://muku.tv/works/detail/MUDR408 |
| Muramura (`muramura`) | listing (`list-newest-0.json`) | listing | ok | 50 | 10.63s | https://www.muramura.tv/dyn/phpauto/movie_lists/list_newest_0.json |
| Muramura (`muramura`) | detail (`detail-103126_1273.json`) | detail | not-checked (request_limit) | 0 | 0.46s | https://www.muramura.tv/dyn/phpauto/movie_details/movie_id/103126_1273.json |
| MUTEKI (`muteki`) | listing (`listing-page1.html`) | listing | ok | 1 | 11.97s | https://www.mutekimuteki.com/works/list/release/ |
| MUTEKI (`muteki`) | detail (`detail-tek102.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.mutekimuteki.com/works/detail/tek102/ |
| MVG (`mvg`) | listing (`list-2026-11-17.html`) | listing | ok | 4 | 10.74s | https://mvg.jp/works/list/date/2026-11-17 |
| MVG (`mvg`) | detail (`detail-MVSD710.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://mvg.jp/works/detail/MVSD710 |
| NANPA JAPAN (`nanpajapan`) | listing (`list-2026-12-01.html`) | listing | ok | 6 | 10.98s | https://nanpa-japan.jp/works/list/date/2026-12-01 |
| NANPA JAPAN (`nanpajapan`) | detail (`detail-NPJS299.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://nanpa-japan.jp/works/detail/NPJS299 |
| Next11 (`next11`) | listing (`list-ym-202611.html`) | listing | ok | 15 | 10.49s | https://next11.co.jp/products/list.php?ym=202611 |
| Next11 (`next11`) | detail (`detail-KIR-080.html`) | detail | not-checked (request_limit) | 0 | 0.23s | https://next11.co.jp/products/detail.php?product_id=13788 |
| 141Jav (`141jav`) | listing (`listing-page-1.html`) | listing | ok | 10 | 10.96s | https://www.141jav.com/new?page=1 |
| 141Jav (`141jav`) | detail (`detail-sample-1.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.141jav.com/torrent/JUQ521 |
| 1Pondo (`onepondo`) | listing (`list-newest-0.json`) | listing | network (network) | 0 | 22.41s | https://en.1pondo.tv/dyn/phpauto/movie_lists/list_newest_0.json |
| 1Pondo (`onepondo`) | detail (`detail-060426_001.json`) | detail | not-checked (request_limit) | 0 | 0.00s | https://en.1pondo.tv/dyn/phpauto/movie_details/movie_id/060426_001.json |
| OneJav (`onejav`) | listing (`listing-page-1.html`) | listing | not-checked (fallback_unsupported) | 0 | 10.17s | https://onejav.com/new |
| OneJav (`onejav`) | detail (`detail-sample-1.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://onejav.com/torrent/ymds282 |
| OPERA (`opera`) | listing (`list-2023-08-22.html`) | listing | ok | 1 | 10.73s | https://av-opera.jp/works/list/date/2023-08-22 |
| OPERA (`opera`) | detail (`detail-OPBD209.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://av-opera.jp/works/detail/OPBD209 |
| OPPAI (`oppai`) | listing (`list-2026-11-17.html`) | listing | ok | 7 | 11.77s | https://oppai-av.com/works/list/date/2026-11-17 |
| OPPAI (`oppai`) | detail (`detail-PPPE459.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://oppai-av.com/works/detail/PPPE459 |
| Pacopacomama (`pacopacomama`) | listing (`list-newest-0.json`) | listing | ok | 50 | 11.04s | https://www.pacopacomama.com/dyn/phpauto/movie_lists/list_newest_0.json |
| Pacopacomama (`pacopacomama`) | detail (`detail-110326_100.json`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.pacopacomama.com/dyn/phpauto/movie_details/movie_id/110326_100.json |
| PLUM (`plum`) | listing (`listing-home.html`) | listing | not-checked (request_limit) | 0 | 10.53s | https://www.plum-web.com/ |
| PLUM (`plum`) | detail (`detail-puw070.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.plum-web.com/title/puw070/ |
| Premium Beauty (`premiumbeauty`) | listing (`list-2026-11-17.html`) | listing | ok | 8 | 10.59s | https://premium-beauty.com/works/list/date/2026-11-17 |
| Premium Beauty (`premiumbeauty`) | detail (`detail-PRED896.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://premium-beauty.com/works/detail/PRED896 |
| ROCKET (`rocket`) | listing (`works.html`) | listing | ok | 24 | 12.20s | https://rocket-inc.net/works/ |
| ROCKET (`rocket`) | detail (`detail-rctd-774.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://rocket-inc.net/works/rctd-774/ |
| ROOKIE (`rookie`) | listing (`list-2026-10-13.html`) | listing | ok | 6 | 12.29s | https://rookie-av.jp/works/list/date/2026-10-13 |
| ROOKIE (`rookie`) | detail (`detail-RKI773.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://rookie-av.jp/works/detail/RKI773 |
| S1 NO.1 STYLE (`s1`) | listing (`list-2026-11-24.html`) | listing | ok | 23 | 11.66s | https://s1s1s1.com/works/list/date/2026-11-24 |
| S1 NO.1 STYLE (`s1`) | detail (`detail-SNOS524.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://s1s1s1.com/works/detail/SNOS524 |
| Silk Labo (`silklabo`) | listing (`listing-page-1.html`) | listing | ok | 32 | 10.47s | https://www.silklabo.com/product_list/ |
| Silk Labo (`silklabo`) | detail (`detail-27039.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.silklabo.com/detail/27039/ |
| Smart FLASH (`smartflash`) | listing (`listing-idol-page1.html`) | listing | ok | 30 | 11.09s | https://smart-flash.jp/gravure/idol/ |
| Smart FLASH (`smartflash`) | detail (`gallery-432913.html`) | gallery | not-checked (request_limit) | 0 | 0.62s | https://smart-flash.jp/gravure/idol/432913/ |
| Tameike Goro (`tameikegoro`) | listing (`list-2026-11-17.html`) | listing | ok | 15 | 11.37s | https://tameikegoro.jp/works/list/date/2026-11-17 |
| Tameike Goro (`tameikegoro`) | detail (`detail-MFYD231.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://tameikegoro.jp/works/detail/MFYD231 |
| 10Musume (`10musume`) | listing (`list-newest-0.json`) | listing | ok | 50 | 11.18s | https://www.10musume.com/dyn/phpauto/movie_lists/list_newest_0.json |
| 10Musume (`10musume`) | detail (`detail-110326_01.json`) | detail | not-checked (request_limit) | 0 | 0.00s | https://www.10musume.com/dyn/phpauto/movie_details/movie_id/110326_01.json |
| Tokuma Shoten (`tokuma`) | listing (`listing-page1.html`) | listing | ok | 12 | 10.85s | https://www.tokuma.jp/search/new.html |
| Tokuma Shoten (`tokuma`) | detail (`detail-b681270.html`) | detail | not-checked (request_limit) | 0 | 0.36s | https://www.tokuma.jp/book/b681270.html |
| Tokyo-Hot (`tokyohot`) | listing (`listing.html`) | listing | ok | 80 | 13.94s | https://my.tokyo-hot.com/index |
| Tokyo-Hot (`tokyohot`) | detail (`detail.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://my.tokyo-hot.com/product/n1700/ |
| TO-SATSU (`tosatsu`) | listing (`list-2026-12-01.html`) | listing | ok | 3 | 11.48s | https://to-satsu.com/works/list/date/2026-12-01 |
| TO-SATSU (`tosatsu`) | detail (`detail-CLUB947.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://to-satsu.com/works/detail/CLUB947 |
| V-AV (`vav`) | listing (`list-2022-05-03.html`) | listing | ok | 1 | 10.81s | https://v-av.com/works/list/date/2022-05-03 |
| V-AV (`vav`) | detail (`detail-VVVD205.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://v-av.com/works/detail/VVVD205 |
| VENUS (`venus`) | listing (`listing-page-1.html`) | listing | not-checked (request_limit) | 0 | 14.08s | https://venus-av.com/all |
| VENUS (`venus`) | detail (`detail-VENX-388.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://venus-av.com/products/%e3%80%8c%e3%81%8a%e3%81%b0%e3%81%95%e3%82%93%e3%81%ae%e4%b8%8b%e7%9d%80%e3%81%a7%e8%88%88%e5%a5%ae%e3%81%99%e3%82%8b%e3%81%ae%ef%bc%9f%e3%80%8d%e8%84%b1%e3%81%8e%e3%81%9f%e3%81%a6%e3%81%ae-44/ |
| WANZ FACTORY (`wanz`) | listing (`list-2026-12-01.html`) | listing | ok | 11 | 11.30s | https://wanz-factory.com/works/list/date/2026-12-01 |
| WANZ FACTORY (`wanz`) | detail (`detail-WAAA716.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://wanz-factory.com/works/detail/WAAA716 |
| Warashi (`warashi`) | listing (`listing_page1.html`) | performer_listing | network (network) | 0 | 20.30s | https://warashi-asian-pornstars.fr/en/s-2-2/female-pornstars/toutes/all/page/1 |
| Warashi (`warashi`) | detail (`detail_yua_mikami.html`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://warashi-asian-pornstars.fr/en/s-2-0/yua-mikami/asian-female-pornstar/1234 |
| Wikidata (`wikidata`) | listing (`list-offset-0.json`) | listing | not-checked (request_limit) | 0 | 12.97s | https://query.wikidata.org/sparql?query=SELECT%20%3Fp%20WHERE%20%7B%20%3Fp%20wdt%3AP106%20wd%3AQ488111%3B%20wdt%3AP27%20wd%3AQ17.%20%7D%20ORDER%20BY%20%3Fp%20LIMIT%205%20OFFSET%200&format=json |
| Wikidata (`wikidata`) | detail (`entity-Q22128111.json`) | performer_detail | not-checked (request_limit) | 0 | 0.00s | https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q22128111&props=labels%7Cdescriptions%7Caliases%7Cclaims%7Csitelinks%2Furls&languages=ja%7Cen&sitefilter=jawiki%7Cenwiki&format=json |
| WPB Web Gravure (`wpb`) | listing (`listing-page-1.html`) | listing | network (network) | 0 | 20.99s | https://wpb.shueisha.co.jp/gravure/list/ |
| WPB Web Gravure (`wpb`) | detail (`gallery-20261108-133432.html`) | gallery | not-checked (request_limit) | 0 | 0.00s | https://wpb.shueisha.co.jp/gravure/20261108-133432/ |
| XCITY (`xcity`) | listing (`listing_page1.html`) | listing | network (network) | 0 | 15.00s | https://xxx.xcity.jp/avod/list/ |
| XCITY (`xcity`) | detail (`detail-placeholder.html`) | detail | not-checked (request_limit) | 0 | 0.00s | https://xxx.xcity.jp/avod/detail/?id=186022 |
