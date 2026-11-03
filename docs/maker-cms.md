# Maker CMS family

`Adapters/Shared/MakerCms` parses public server-rendered release-date indexes,
date-group movie cards, movie details and actress profiles. S1 is the first
consumer. `S1/Site.php` supplies configuration; `S1Crawler` supplies the slug.
The manifest registers capabilities and URL samples through the existing registry.
IDEAPOCKET (`Adapters/Ideapocket`) is the second consumer: a `Site` config, a
crawler slug and a manifest only. MOODYZ (`Adapters/Moodyz`) is the third consumer.
ATTACKERS (`Adapters/Attackers`, fixtures captured 2026-11-03 from public pages without credentials) is a further Site config + crawler + manifest consumer; no base change was needed.
Premium Beauty (`Adapters/PremiumBeauty`, fixtures captured 2026-11-03 from public pages without credentials) is likewise a Site config + crawler + manifest consumer; no base change was needed.
Other sister sites are registered by their own tickets.

`SiteConfig` controls the origin, maker and date/list/detail/actress route prefixes.
The shared URL rules use those prefixes, including performer-detail inference.
A date-index listing page selects one release group per requested page in source
order; direct date-group URLs return their cards. Pagination is terminal when the
index has no later date or the direct group has no next link.

Movies retain site IDs, title, synopsis, ISO release date, duration in minutes,
ordered gallery images, performers and genre tags. Metadata retains series and
genre IDs/links, label links (`label`), release links, director, each physical format's code/runtime/price,
sample video/poster, purchase links and release caveat. Labeled source rows are
also retained. Listing metadata preserves withheld ensemble counts, card badges,
displayed range and date-group counts. Images use lazy-load attributes before src.

Actresses retain Japanese and Latin names, aliases, profile images, ISO birthday,
height, measurements, every labeled profile row, profile/social links and related
works with titles/images and the displayed work range. Desktop/mobile duplicate
images are deduplicated. Global navigation, social links and recommended movies
are excluded using content-scoped selectors.

The real HTML fixtures were captured from public S1 pages on 2026-11-03, without
credentials. Data is rendered in HTML; the inspected work/profile scripts only
initialize sliders. No authenticated endpoint is supported. Unit/feature field
assertions cover these captures, an empty biography and another configured host.

IDEAPOCKET (fixtures captured 2026-11-03 from public pages, no credentials) shows
the same markup. Differences handled in the base: the `レーベル` row is kept as
`metadata.label` (only when present), the `---` director placeholder becomes null,
listing metadata carries `search_key` (the `p-search__key` heading, e.g. the date
or genre name), listing movie URLs are canonical (tracking query removed) and
actress work URLs drop `page_from`/`sys_code`. Besides date pages, URL inference
and listing pagination (`rel="next"`) cover `/works/list/{genre,label,series}/<id>`.

MOODYZ fixtures (`tests/Fixtures/moodyz`) were captured from the public site on 2026-11-03 without credentials: the date index, two date-group listings, one detail and one actress profile. MOODYZ profiles may omit the birthday; `birth_date_raw` is then null.

Madonna (`https://madonna-av.com`) fixtures (`tests/Fixtures/madonna`) were captured from the public site on 2026-11-03 without credentials: the date index, the 2026-11-10 and 2026-12-01 date listings, one detail (JUR920) and one actress profile (871344). No shared code was changed; actress profiles may omit the birthday (`birth_date_raw` is then null).

kawaii* (`https://kawaiikawaii.jp`) fixtures (`tests/Fixtures/kawaiikawaii`) were captured from the public site on 2026-11-03 without credentials: the date index, the 2026-12-01 and 2026-11-03 date listings, one detail (KWBD444, DVD + Blu-ray) and one actress profile (876969). No shared code was changed; `metadata.maker` is `kawaii*`.
DASDAS (`Adapters/Dasdas`, fixtures captured 2026-11-03 from public pages, no
credentials) needs no base change: the same rows are parsed, the `レーベル` row is
absent so `metadata.label` is omitted, and an empty `シリーズ` row yields an empty
series list. The site publishes two actress social links with a doubled host in the
`href` (`https://x.com/https://x.com/<handle>`); they are kept as published and the
handle is exposed as the link `external_id`.
