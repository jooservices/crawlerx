# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. Pacopacomama supplies a second thin configuration and URL detection rules.

Configuration includes the API base URL, asset base URL, movie path template and
URL regex, gallery JSON path template, gallery image path, maker, diagnostic label
and gallery metadata source. Templates substitute `{id}`. Common list and detail
API paths remain `/dyn/phpauto/movie_lists/list_<order>_<offset>.json` and
`/dyn/phpauto/movie_details/movie_id/<id>.json`.

OnePondo's configuration lives in `Adapters/OnePondo/Site.php`. Existing OnePondo
parser and normalizer classes remain compatibility wrappers. The shared parser
preserves row mapping, ordering, pagination and optional-gallery handling.

`OnePondoFamilyTest` uses existing JSON fixtures and a generated alternate host to
verify independent movie, API, gallery and asset URLs without network requests.

## Pacopacomama

`Adapters/Pacopacomama/Site.php` uses `https://www.pacopacomama.com`, public
`/movies/<MovieID>/` routes, and the common list/detail JSON API. Both two- and
three-digit movie suffixes are accepted. Its manifest registers listing/detail
and supplies the samples used by the registry, source matrix and canary.

Live curl captures on dev-workspaces, 2026-11-03, verified listing and detail
HTTP 200 without a challenge. The listing reports `SplitSize: 50` and
`TotalRows: 3119`. Page 2 requests `list_newest_50.json`; callers can read the
exact count from `result.pagination.totalRows`. This optional internal DTO
property is hidden during serialization, preserving existing pagination output.
OnePondo leaves it null and retains its existing serialized DTO contract.

For the captured movie `110326_100`, the real site's `app.ab9a4b26.js` and
`movieDetail.928fdcb7.js` select `/dyn/dla/json/movie_gallery/<id>.json` when
`Gallery` is true; curl confirmed HTTP 200 with three public sample rows.
`/dyn/phpauto/movie_galleries/movie_id/110326_100.json` returned 404. Contrary
to the earlier ticket observation, the 1Pondo-style path works for this movie.
No assumption is made about older legacy-only movies. The parser keeps only
unprotected `/sample/` images and never fetches member media or archives.

Fixtures under `tests/Fixtures/pacopacomama/` are real curl captures with SHA-256
provenance sidecars. The listing keeps its first two complete records to remain
small, preserving the original totals; detail/gallery are complete captures.
The gallery is a secondary detail response, not a separately declared capability.

Field mapping follows the shared base: MovieID to external_id/code; TitleEn
preferred over Title with both retained in metadata.titles; DescEn/Desc similarly;
Release to ISO date; Duration seconds to rounded minutes; actress IDs and English/
Japanese names, meaningful sizes/ages; UC/UCNAME/UCNAMEEn to tag IDs and bilingual
tags; series ID and bilingual titles; rating when present; cover/thumbnail/stream
cover; year, MetaMovieID, CanStream and HasGallery; all public sample qualities
and URLs, plus sample_video_url; and public gallery screenshots. Alternate thumb
fields in this capture have the same URL as ThumbHigh. Null rating is omitted.
Administrative flags, duplicate Actor/ActorID/UcNameList representations, member
files, archive flags, site IDs and zero-valued PPV pricing are not catalog fields.
Gallery availability is determined by the actual response, not HasGallery (false
for this capture even though Gallery is true).
