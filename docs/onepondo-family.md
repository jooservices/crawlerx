# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. OnePondo, 10musume and Pacopacomama are
registered adapters built on this base.

Configuration includes the API base URL, asset base URL, movie path template and
URL regex, gallery JSON path template, gallery image path, maker, diagnostic label,
gallery metadata source and the movie id pattern. Templates substitute `{id}`.
Common list and detail API paths remain
`/dyn/phpauto/movie_lists/list_<order>_<offset>.json` and
`/dyn/phpauto/movie_details/movie_id/<id>.json`. 10musume differs from OnePondo
only in its host, site id and two-digit movie id suffix (`\d{6}_\d{2}`).

OnePondo's configuration lives in `Adapters/OnePondo/Site.php` and 10musume's in
`Adapters/TenMusume/Site.php`. Existing OnePondo parser and normalizer classes
remain compatibility wrappers. The shared parser preserves row mapping, ordering,
pagination and optional-gallery handling.

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
