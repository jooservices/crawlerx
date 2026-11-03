# Caribbeancom family adapters

`caribbeancom` and `caribbeancompr` share one listing and one detail parser
(`Adapters/CaribbeanCom/Types`). A `Profile` (`Adapters/CaribbeanCom/Profile.php`) holds what differs per
site: hosts, detail path pattern, listing url, spec row and title selectors, spec labels (English and
Japanese), poster file names and the optional description, purchase box and player flag capture. A concrete
crawler extends `AbstractCaribbeanCrawler` and returns its profile.

## caribbeancompr

- English: `https://en.caribbeancompr.com/eng/listpages/N.html` and `/eng/moviepages/<id>/index.html`.
- Japanese: `https://www.caribbeancompr.com/moviepages/<id>/index.html` (EUC-JP). The Japanese page adds
  `description` and `metadata.maker` (Studio) and carries Japanese tags.
- Detail fields: title, code/id, performers (name and url; the site has no performer ids), ISO date, duration in
  minutes, tags, cover, screenshots, `metadata.downloads`, `sample_video_url`, `price` (purchase),
  `rental_price`, `available_resolutions`, `movie_seq` and `flags` (player availability flags).
- Listing fields: id, title, cover, `metadata.price` (starting price), next page and last page.
- Related-movie blocks, navigation and help links are not read.
