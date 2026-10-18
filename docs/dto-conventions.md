# DTO conventions for movie and performer data

`MovieDto`, `PerformerDto` and `GalleryDto` have a few first-class properties. Everything else an adapter extracts goes into `metadata`. To make that data readable the same way for every site, adapters use the keys below (constants and builders in `JOOservices\CrawlerX\Support\MovieMeta`). A missing value is absent, never an empty string or an empty list (`MovieMeta::filter()`).

## First-class movie properties

| Property | Rule |
|---|---|
| `title` | Title as shown by the site (no site suffix). |
| `code` | The catalogue code (`SSIS-001`, `FC2-PPV-123`, `080826-001`). |
| `cover_url` | The cover of this movie, never a site logo or a sprite. |
| `description` | The synopsis written for the movie, never the site's SEO text. |
| `date` | Release date, ISO `YYYY-MM-DD`. A post or upload date goes to `metadata.posted_date`. |
| `duration` | Whole minutes. |
| `performers` | List of `{external_id, name, url}` (`MovieMeta::performer()`), real performers only (no menu, index or maker links). |
| `tags` | Genre or tag names as shown on the movie's own page. |
| `screenshots` | `{url, thumbnail_url, position}`. |

## Standard `metadata` keys (movie)

| Key | Type | Meaning |
|---|---|---|
| `maker`, `label`, `series`, `director` | string | Publisher data. If a site shows several directors, join them with `, `. |
| `rating` | `{score, scale, votes}` | `MovieMeta::rating()`; scale is the maximum (5, 10), `null` if unknown. |
| `magnets` | list | `MovieMeta::magnet()`: `{name, url, size_label, size_bytes, date, tags}`; `url` is the `magnet:` link. |
| `downloads` | list | Other download options (torrent file, file sizes per resolution): `{type, url, quality, size_label, size_bytes}`. |
| `sample_video_url` | string | Preview or trailer. |
| `price` | `{amount, currency, label}` | `MovieMeta::price()`. |
| `seller` | string | Marketplace seller or brand. |
| `views`, `likes`, `favorite_count`, `comment_count` | int | Popularity counters. |
| `titles` | `{ja, en, ...}` | Titles by language when the site offers more than one. |
| `original_title` | string | Original-language title. |
| `release_date_raw` | string | The date text as shown, when the ISO conversion loses information. |
| `posted_date` | string | Post or upload date, ISO. |
| `fields` | map | Raw `label => value` pairs of the info table, for fields without a standard key. |

Site-specific extras keep their own descriptive key; do not rename a standard one.

## Dates and numbers

`MovieMeta::isoDate()` reads year-first dates (`2026-08-14`, `2026/8/4`, `2026年08月 14日`, `2026.10.16`); `MovieMeta::isoDateMonthFirst()` reads `09/01/2026`. `MovieMeta::durationMinutes()` reads `120分`, `120 min`, `160 minute(s)`, `1:02:12`, `48分 33秒`.
