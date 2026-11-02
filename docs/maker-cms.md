# Maker CMS family

`Adapters/Shared/MakerCms` parses public server-rendered release-date indexes,
date-group movie cards, movie details and actress profiles. S1 is the first
consumer. `S1/Site.php` supplies configuration; `S1Crawler` supplies the slug.
The manifest registers capabilities and URL samples through the existing registry.
Sister sites are deliberately not registered by this change.

`SiteConfig` controls the origin, maker and date/list/detail/actress route prefixes.
The shared URL rules use those prefixes, including performer-detail inference.
A date-index listing page selects one release group per requested page in source
order; direct date-group URLs return their cards. Pagination is terminal when the
index has no later date or the direct group has no next link.

Movies retain site IDs, title, synopsis, ISO release date, duration in minutes,
ordered gallery images, performers and genre tags. Metadata retains series and
genre IDs/links, release links, director, each physical format's code/runtime/price,
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
