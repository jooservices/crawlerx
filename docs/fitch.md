# Fitch Maker CMS adapter

Fitch uses the existing `Shared/MakerCms` parsers without overrides. The shared
listing mapper additionally associates release-catalog cards with their own
dated section headings; URL rules include release/reserve and keyword search. Its Site
configuration supplies only the canonical host and maker. Manifest discovery
registers `fitch`; shared detection supports date indexes, date/genre/label/series
listings, release/reserve catalogs, keyword search, movie details and actress
profiles without overrides.

## Field inventory

Public, server-rendered captures were taken on dev-workspaces on 2026-11-03.
No catalog JSON request was found in the page or the detail script (`work.js`,
slider initialization only). Purchase URLs are retained without fetching their
destinations. Navigation and recommended movies are excluded.

| Source | DTO destination |
| --- | --- |
| Product URL and ID | url, external_id, code |
| Title / synopsis | title / description |
| Actress names, IDs and links | performers |
| Release date and link | date (ISO), metadata.release_links |
| Series / label links and IDs | metadata.series / metadata.label |
| Genres and IDs | tags, metadata.genres |
| Director | metadata.director |
| All format codes, runtimes and tax-inclusive prices | metadata.formats; duration in minutes |
| Every labelled row, including empty values | metadata.source_fields |
| Cover and complete public image gallery | cover_url, screenshots |
| Sample video / poster | metadata.sample_video_url / sample_video_poster_url |
| Purchase link labels, URLs and displayed prices | metadata.purchase_links |
| Release disclaimer | metadata.release_note |
| Site identity | metadata.maker |
| Card URL, ID, title, thumbnail and actresses | url, external_id, code, title, cover_url, performers |
| Card badge and aggregate performer count | metadata.badge / performer_count |
| Date group name, date, ID, URL and work count | metadata.release_group |
| Search label, reserve release period, visible/total range | metadata.search_key / listing_range |
| Release section date, label and count for each card | date (ISO), metadata.release_group, metadata.search_key |
| Next link and date groups | pagination |
| Actress Japanese/Latin names and ID | name_japanese, name, aliases, external_id, url |
| Actress photos | profile_image_url, metadata.images |
| All actress profile labels | raw_profile; birth_date_raw, height_raw, size_raw when present |
| Actress external/social links | metadata.profile_links / social_links |
| Actress filmography titles, IDs, links, images, range | metadata.works / work_range |

DEAB010 and FPRE247 expose all nine labelled detail rows; their series is empty
and their only offered format is DVD. The shared S1 fixtures also exercise
Blu-ray; no extra format is invented for Fitch. Actress 876895 has an empty bio;
869733 offers `3サイズ` (`B--cm (-) W--cm H100cm`), retained verbatim. Missing
birthdays and heights remain null. No additional language variant is offered
for movie text; actress names include Japanese and Latin variants.

`FitchTest` asserts every nested field and array element against reviewed fixture
expectations, separately compares all labelled source rows against the DOM, and
checks date walking, registry discovery, URL inference and recommendation exclusion.
Unit and Feature suites both exercise the adapter. Existing family tests remain
the regression coverage for S1, MOODYZ and IDEAPOCKET.

## Capture provenance

HTML files are unmodified HTTP captures. Expectations are checked-in DTO values,
with one assertion for every scalar, empty array and array shape. The manifest
uses direct list samples so a canary stays within three requests per capability;
supplemental captures live in `tests/FamilyFixtures/fitch` and are hash-verified
by the adapter tests. The date index is exercised offline, including its extra
listing hop. Live samples select one URL per capability so matrix, canary and
the independent URL-driven comparison together stay within three requests.

| Fixture | URL | SHA-256 |
| --- | --- | --- |
| `actress-869733.html` | https://fitch-av.com/actress/detail/869733 | `a937db6093f4d4e8426d2c3d0f6aaefe4d02ec4d7893346765422edc558fdc9f` |
| `actress-876895.html` | https://fitch-av.com/actress/detail/876895 | `be71e2185d56ef6c93178bef333e4bcdfa287ecac6d76fb4c72b32273f9ac310` |
| `dates.html` | https://fitch-av.com/works/date | `5af03b7af6744b297e8d27e128657c49ee561b518c86cd44ca976e6ba1703bb3` |
| `detail-DEAB010.html` | https://fitch-av.com/works/detail/DEAB010 | `4f677f029afb214d3f9a834dccd825f5c82f3d439f2e662ffa249a3c12249b41` |
| `detail-FPRE247.html` | https://fitch-av.com/works/detail/FPRE247 | `9c1fb53f8794926524fa31f2e36832e3453c17d0992abba36adc974ef32c281f` |
| `list-2026-12-01.html` | https://fitch-av.com/works/list/date/2026-12-01 | `975d0adb42a0e2347e387d7c78e5547e059a84085049ab7dd887b638af354061` |
| `list-label-10123.html` | https://fitch-av.com/works/list/label/10123 | `68135ce6805628ad22c4cdd502539d1e5342cb039350cdeea3b1affd38ce4080` |
| `list-release.html` | https://fitch-av.com/works/list/release | `178b70d29d5d896d86d3fdcfdbf137ae5f2de27d860adf242aa2a3f961854663` |
| `list-reserve.html` | https://fitch-av.com/works/list/reserve | `1dafada26d567fafccacd567f00eb0895bed2af269d52add532814cce201c83f` |
| `list-search.html` | https://fitch-av.com/search/list?keyword=DEAB010 | `01f626891badc4fbceae0589dd3ec0980bc9c997d42dc63597db9054b318cf64` |

Release-catalog fixtures cover three independent date groups (7, 4 and 4 works).
Reserve captures retain the displayed period without inventing a date for each
card; keyword captures preserve the query label and matching work.
