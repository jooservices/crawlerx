# AV-Wiki adapter

The `avwiki` adapter reads the public WordPress REST API at `av-wiki.net` and
uses the post's public article page for its product images and retailer links.
No account, token or application key is required.

`CrawlerX::url('https://av-wiki.net/wp-json/wp/v2/posts?per_page=100&page=1&_embed=1')` fetches the newest posts from
`/wp-json/wp/v2/posts` with embedded taxonomy terms. A detail URL such as
`https://av-wiki.net/200gana-3482/` combines the post API record, the article's
`section.article-body`, the article's product fields, and its category term.
Actor tags become `MovieDto.performers`; list pages resolve the public tag and
category records so actor profiles and taxonomy descriptions are retained.

Release dates use ISO `YYYY-MM-DD`; WordPress post and modification dates use
ISO 8601 timestamps. Runtime fields from WordPress remain under
`metadata.wordpress`; normalized aliases expose author, featured-media ID,
content/excerpt protection flags, and category/tag ID lists. Product fields
from `dl.dltable` remain available as `metadata.fields`. The adapter keeps
article text, retailer `metadata.links`, cover and sample images, tag/profile
data, and category records under `metadata.categories`. Category descriptions
are plain text. It removes related-page links and share controls from the parsed
article. AV-Wiki does not provide a movie synopsis or duration in the captured
records, so those DTO fields remain absent.

`tests/Fixtures/avwiki/` contains captured public JSON and HTML. The manifest
registers listing and detail inputs separately from supporting API responses;
supporting fixtures are used by offline tests and do not become source-matrix
targets.
