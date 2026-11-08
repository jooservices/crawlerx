<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavMenu;

final class Selectors
{
    public const CARD = '.category-page.video-list-item';

    public const CARD_LINK = '.card-body a[href]';

    public const CARD_TITLE = '.card-body p.card-text';

    public const CARD_DATE = '.card-body span.text-muted';

    public const CARD_COVER = 'img.card-img-top';

    public const PERFORMER_NAME = 'h6';

    public const BADGE_ONLINE = '.badge.bg-success';

    public const BADGE_NEW = '.badge.bg-danger';

    public const BADGE_DOWNLOAD = '.badge.bg-primary';

    public const PAGINATION_CURRENT = 'ul.pagination li.active .page-link';

    public const PAGINATION_LINKS = 'ul.pagination li.page-item a.page-link[href]';

    public const ARCHIVE_HEADING = '.bg-archive h2';

    public const ARCHIVE_TITLE = '.bg-archive h2 .color-light-yellow';

    public const DETAIL_INFO_ROWS = '.left-wrapper .card-body > div.d-flex';

    public const DETAIL_CODE_PREFIX = '.code a';

    public const DETAIL_DIRECTOR = '.director a';

    public const DETAIL_MAKER = '.maker a';

    public const DETAIL_SERIES = '.series a';

    public const DETAIL_GENRES = 'a.genre';

    public const DETAIL_ACTRESSES = 'a.actress';

    public const DETAIL_COVER = 'meta[property="og:image"]';

    public const DETAIL_POSTER = 'video#seo-main-video';

    public const DETAIL_JSON_LD = 'script[type="application/ld+json"]';

    public const DETAIL_STREAMS = '#player-tab a[data-m3u8]';

    public const DETAIL_MAGNET_ROWS = 'table.magnet-table tbody tr';

    public const DETAIL_ALTERNATES = 'link[rel="alternate"][hreflang]';

    public const DETAIL_TITLE = 'h1 strong';
}
