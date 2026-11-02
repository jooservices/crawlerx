# 1Pondo-family JSON adapters

`Adapters/Shared/OnePondoFamily/FamilyCrawler` provides the common listing and
movie-detail JSON backend. Concrete adapters supply `siteConfig()` returning a
`SiteConfig`, their name and URL detection rules. The OnePondo and Muramura
adapters use this shared base.

Configuration includes the API base URL, asset base URL, movie path template and
URL regex, gallery JSON path template, gallery image path, maker, diagnostic label
and gallery metadata source. Templates substitute `{id}`. Common list and detail
API paths remain `/dyn/phpauto/movie_lists/list_<order>_<offset>.json` and
`/dyn/phpauto/movie_details/movie_id/<id>.json`.

OnePondo's configuration lives in `Adapters/OnePondo/Site.php`; Muramura's lives
in `Adapters/Muramura/Site.php`. Existing OnePondo parser and normalizer classes
remain compatibility wrappers. The shared parser preserves row mapping,
ordering, pagination and optional-gallery handling. The movie ID pattern is
configurable and defaults to OnePondo's existing six-digit plus three-digit
format; Muramura accepts two to four digits after the underscore.
The shared `has_gallery` field reads `HasGallery` by default; Muramura selects
its distinct `Gallery` flag, leaving OnePondo's existing DTO value unchanged.

Muramura's frontend uses `/movies/{id}/` and selects its gallery JSON from
`/dyn/phpauto/movie_galleries/movie_id/{id}.json`; the OnePondo
`/dyn/dla/json/movie_gallery/{id}.json` route is not used. The live Muramura
fixture has `Gallery: false`, and this movie's gallery endpoint returns 404, so
its parsed `screenshots` list is empty. The frontend poster at
`/assets/sample/{id}/str.jpg` is a cover image, not a screenshot gallery.

`OnePondoFamilyTest` uses existing JSON fixtures and a generated alternate host to
verify independent movie, API, gallery and asset URLs without network requests.
