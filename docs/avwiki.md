# AV-Wiki adapter

The `avwiki` adapter reads the public WordPress REST API at `av-wiki.net`. No
account, token or application key is required.

## Request budget

av-wiki.net rate-limits per client: the first request answers in about 0.5 s and
every following request (even for the same URL) is held for about 10.4 s before
the full `200` response arrives. The adapter therefore issues the minimum:

- **Listing**: one request per page, `/wp-json/wp/v2/posts?per_page=100&page=N&_embed=1`.
- **Detail**: one REST request, `/wp-json/wp/v2/posts?slug=<slug>&_embed=1`. Categories
  and tags are resolved from the embedded `wp:term` groups of that response; the
  `/categories` and `/tags` endpoints are never called. The article page is not
  requested by the adapter. When a detail URL is the HTML page, the orchestrator
  already fetches it once (fast, first request) and the adapter reuses that body
  for the fields the REST payload lacks: the cover image (`awsimgsrc.dmm.co.jp`
  `...pl.jpg`, injected by the theme and absent from `content.rendered`), sample
  images, `h1.entry-title` page heading and the page date. A REST detail URL
  (`...posts?slug=...` or `.../posts/<id>`) is a single request in total; the JSON
  body is never treated as page HTML. Images are then read from `content.rendered`
  and the cover from the embedded `wp:featuredmedia` `source_url` when present. The
  live REST payload currently carries neither, so a REST-only detail has no cover;
  use the page URL (`/slug/`, `?p=<id>`) when the cover is needed.

Timeouts and pacing: `defaultCrawlConfig.timeout` and `connect_timeout` are 45 s
(the ~10.4 s hold counts as connection wait, and a fixed 5 s connect timeout used
to abort the request while the total `timeout` option was ignored), and
`defaultThrottle.default_gap_seconds` is 12 (min 10) so scheduled crawls keep at
least 10 s between requests to the host. The shared fetch chain caps its own HTTP
attempts at 20 s, which covers the fast first request.

## Fields

`CrawlerX::url('https://av-wiki.net/wp-json/wp/v2/posts?per_page=100&page=1&_embed=1')`
returns the newest posts. Release dates use ISO `YYYY-MM-DD`; WordPress dates use
ISO 8601 timestamps. Every REST post field stays under `metadata.wordpress`.

| DTO field | Source |
| --- | --- |
| `external_id`, `url` (`link`/`guid`), `title` | REST `id`, `link`, `title.rendered` |
| `code` | `dl.dltable` メーカー品番 / MGS / FANZA code in `content.rendered` |
| `date` | `dl.dltable` 配信開始日 (`metadata.release_date_raw`); `metadata.posted_date*` / `modified_date*` from REST dates |
| `performers`, `tags` | embedded `post_tag` terms (id, name, link, slug) |
| `metadata.categories`, `category_ids`, `tag_ids` | embedded `category` terms and REST id lists |
| `metadata.maker`, `label`, `series`, `fields` | `dl.dltable` in `content.rendered` |
| `metadata.author`, `metadata.wordpress` | REST `author` + `_embedded.author`, all post fields (`excerpt`, `content` protection flags, `class_list`, `_links`, ...) |
| `metadata.links`, `article_text` | retailer links and cleaned text of `content.rendered` |
| `cover_url`, `screenshots`, `metadata.titles.page_heading`, `page_date` | article HTML (detail via page URL only) |

Limitations: the embedded tag term carries only `id/link/name/slug/taxonomy`, so
performer profile data that lives in the tag description (birth date, size,
aliases, SNS, profile image) and the term `count` are not returned; reading them
needs one extra `/tags?include=` request per page, which the per-request delay
makes too costly. The excerpt is empty for the sampled posts, and AV-Wiki offers
no synopsis or duration, so those DTO fields stay absent. Listing items have no
cover/sample images because the REST payload has none.

Future release dates: the site publishes posts with release dates ahead of their
publication. The captured listing fixture has REST `date`/`date_gmt` of
`2026-11-06` while `captured_at` is `2026-11-02T22:59:43Z`; the capture clock was
verified correct, so the data is kept unaltered (see `provenance_note` in
`tests/Fixtures/avwiki/listing-page1.json.meta.json`).

`tests/Fixtures/avwiki/` contains captured public JSON and HTML. The REST detail
response is registered as a supporting fixture and does not become a
source-matrix target.
