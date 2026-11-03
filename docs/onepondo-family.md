# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. OnePondo, 10musume and Pacopacomama are
registered adapters built on this base.

Configuration includes the API base URL, asset base URL, movie path template and
URL regex, gallery JSON path template, gallery image path, maker, diagnostic label,
gallery metadata source, movie id pattern and the gallery flag field (default
`HasGallery`). Templates substitute `{id}`.
Common list and detail API paths remain
`/dyn/phpauto/movie_lists/list_<order>_<offset>.json` and
`/dyn/phpauto/movie_details/movie_id/<id>.json`. 10musume differs from OnePondo
only in its host, site id and two-digit movie id suffix (`\d{6}_\d{2}`).

OnePondo's configuration lives in `Adapters/OnePondo/Site.php`, 10musume's in
`Adapters/TenMusume/Site.php`, and Muramura's in `Adapters/Muramura/Site.php`.
Existing OnePondo parser and normalizer classes remain compatibility wrappers.
The shared parser preserves row mapping, ordering, pagination and optional-gallery
handling. Muramura selects its `Gallery` flag; OnePondo and 10musume use the
default `HasGallery` field.

Muramura's movie page uses `/movies/{id}/`, and its gallery JSON path is
`/dyn/phpauto/movie_galleries/movie_id/{id}.json`. The captured sample has
`Gallery: false`; its gallery endpoint was not verified with a live movie whose
gallery is enabled.

`OnePondoFamilyTest` uses existing JSON fixtures and a generated alternate host to
verify independent movie, API, gallery and asset URLs without network requests.

Pacopacomama uses the shared URL detection rules and accepts two- or three-digit
movie ID suffixes through `movieIdPattern`. Pagination derives `lastPage` from
`TotalRows` and `SplitSize`, using the same public pagination DTO as other sites.
Its public gallery is `/dyn/dla/json/movie_gallery/{id}.json`; the real gallery
fixture has its own hash-verified provenance. Only public sample images and videos
are emitted; member-only files are excluded.

The real detail fixture verifies titles and descriptions (including Japanese),
release date, duration, actor names and IDs, tags and IDs, series and IDs, year,
meta movie ID, thumbnail/cover/stream cover, availability flags, sample videos,
and public gallery images. Duplicate source aliases and administrative/member
payloads are excluded from the catalog DTO.

Rechecked on dev-workspaces on 2026-11-03: live detail `110326_100` matches the
fixture, and the site's JavaScript confirms the detail/gallery API paths and
rendered title, description, performer, measurements, series, date and duration
fields. This movie has unknown age/measurements (`0`, `0-0-0`); its separate public
review endpoint returns 404, so no rating is available for this capture. Source
matrix and canary both pass listing/detail using HTTP.

Direct JSON listing URLs retain their order and numeric offset (for example,
`list_weekly_0.json` and `list_newest_50.json`). Pagination derives the current
page from the payload's split size and emits the next JSON offset URL. Public
`/list/?o=...&page=...` routes retain their existing query-based behavior.

## Field completeness and compatibility

Both list rows and detail records use the same explicit inventory in
`Shared/OnePondoFamily/SourceFields`. Existing DTO fields, metadata keys and
values remain unchanged. Additional metadata preserves the source types and
nested key names, including explicit nulls, empty arrays, false and zero; absent
source keys remain absent. `Expire` is normalized to an ISO expiry date (currently null
in the captured records; invalid dates become null); `Release` is normalized to `YYYY-MM-DD` and `Duration`
from seconds to rounded minutes.

The public list/detail JSON on all four family hosts was inspected on
2026-11-03. Each record exposes these 47 keys. No navigation or sidebar content
is included. The four display/technical exceptions are centralized, with reasons,
in the single `IGNORED_KEYS` constant.

| Source field | Movie DTO destination or ignore reason |
| --- | --- |
| `Actor` | metadata.actor (source shape retained) |
| `ActorID` | metadata.actor_ids (source shape retained) |
| `ActressesJa` | performers fallback; metadata.actresses_ja |
| `ActressesEn` | performers fallback; metadata.actresses_en |
| `ActressesList` | performers; metadata.actresses_list (complete original nested record) |
| `AvgRating` | metadata.rating (score/scale) |
| `CanStream` | metadata.can_stream |
| `Conditions` | metadata.conditions (source shape retained) |
| `Desc` | metadata.descriptions.ja / description fallback |
| `DescEn` | description / metadata.descriptions.en |
| `Duration` | duration (rounded minutes) |
| `Expire` | metadata.expires_at (source shape retained) |
| `HasFlash` | Ignored: Obsolete Flash player presentation flag. |
| `NoListDisplay` | Ignored: Site listing visibility control. |
| `SampleExcludeFlag` | Ignored: Site sample display control. |
| `Gallery` | metadata.gallery (source shape retained) |
| `AffZip` | Ignored: Affiliate ZIP presentation flag; not movie content. |
| `HasGallery` | metadata.has_gallery |
| `HasMemberGalleryZip` | metadata.has_member_gallery_zip (source shape retained) |
| `HasSampleGalleryZip` | metadata.has_sample_gallery_zip (source shape retained) |
| `MetaMovieID` | metadata.meta_movie_id |
| `MovieID` | code / external_id |
| `MovieSeq` | metadata.movie_seq (source shape retained) |
| `MovieThumb` | metadata.thumbnail_url / cover_url fallback |
| `RealMetaMovieID` | metadata.real_meta_movie_id (source shape retained) |
| `Release` | date (ISO YYYY-MM-DD) |
| `Series` | metadata.series_titles.ja / series fallback |
| `SeriesEn` | metadata.series_titles.en / series |
| `SeriesID` | metadata.series_id |
| `SiteID` | metadata.site_id (source shape retained) |
| `Status` | metadata.status (source shape retained) |
| `ThumbHigh` | cover_url; metadata.thumbnail_high_url |
| `ThumbLow` | metadata.thumbnail_low_url (source shape retained) |
| `ThumbMed` | cover_url fallback; metadata.thumbnail_medium_url |
| `ThumbUltra` | metadata.stream_cover_url |
| `Title` | metadata.titles.ja / original_title |
| `TitleEn` | title / metadata.titles.en |
| `Type` | metadata.type (source shape retained) |
| `Year` | metadata.movie_year |
| `UC` | metadata.tag_ids |
| `UCNAME` | metadata.tags_ja / tags fallback |
| `UCNAMEEn` | tags |
| `UcNameList` | metadata.category_names (source shape retained) |
| `IsTicketOnly` | metadata.is_ticket_only (source shape retained) |
| `MemberFiles` | metadata.member_files (source shape retained) |
| `SampleFiles` | metadata.sample_streams / sample_video_url; metadata.sample_files (all nested file properties) |
| `PPVPrice` | metadata.ppv_price (source shape retained) |

`member_files` and `sample_files` retain each file's `FileName`, `FileSize`
(bytes), `MetaMovieID`, `SiteID`, `URL`, and any additional nested properties;
file names identify resolutions. Member URLs are metadata only: the crawler
does not download them or access authenticated content. `ppv_price` retains
`Regular`, `Discount`, `Campaign` and any other price properties without guessing
a currency. `category_names` retains category IDs and bilingual `NameEn` /
`NameJa` records. `actresses_list` retains ID-indexed `NameJa`, `NameEn`, `Sizes`,
`Age` and additional source properties, including sentinel values that normalized
performers omit. Separate language arrays retain names even when the normalized
performer list uses the preferred language.

The listing envelope's `Rows` becomes movie items; `TotalRows` and `SplitSize`
drive pagination. The optional gallery endpoint's public, unprotected
`Rows[].Img` entries become screenshots with URL and position; member images
are skipped.

`OnePondoFamilyCompletenessTest` scans fixture directories and selects family
crawlers through the manifest registry, so later family adapters are included
automatically. Standalone public pacopacomama/muramura captures in `tests/FamilyFixtures` (outside the manifest-checked `tests/Fixtures`) also test this
shared mapper before their adapters merge; no new site is registered here. Each
source field has a separate real-fixture assertion. Unknown keys fail the guard.
`tests/FamilyFixtures/onepondo-family-legacy-hashes.json` freezes serialized pre-change movie DTOs
from develop commit `8042c36` for every captured list/detail row, with a common
OnePondo config. The regression test projects only the original metadata keys
and compares SHA-256 hashes, retaining key order, types, nulls and values.
