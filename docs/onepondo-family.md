# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. OnePondo and 10musume are
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
