# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. No sister sites are registered
by this extraction.

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
