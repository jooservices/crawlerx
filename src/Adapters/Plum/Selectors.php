<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Plum;

final class Selectors
{
    public const LISTING_CARDS = 'section.content_wrap ul > li > div.d-flex.flex-wrap';

    public const LISTING_LINK = 'a[href*="/title/"]';

    public const LISTING_BADGE = '.badge-primary';

    public const LISTING_TITLE = 'a > span.text-white';

    public const LISTING_CAST = 'p.wordbreak';

    public const LISTING_IMAGE = 'img';

    public const PAGE_LINKS = 'a.page-numbers[href]';

    public const PAGE_CURRENT = 'span.page-numbers.current';

    public const LINK_NEXT = 'link[rel="next"]';

    public const DETAIL_TITLE = 'h1 span.h3';

    public const DETAIL_DESCRIPTION = 'p[itemprop="description"]';

    public const DETAIL_COVER = 'div.item_gallery a[itemprop="image"]';

    public const DETAIL_ROWS = 'div.u_pc ul[itemprop="offers"] > li';

    public const DETAIL_ROW_LABEL = 'div:nth-of-type(1) p';

    public const DETAIL_ROW_VALUE = 'div:nth-of-type(2)';

    public const DETAIL_GTIN = 'ul[itemprop="offers"] meta[itemprop="gtin13"]';

    public const DETAIL_VIDEO = 'video';

    public const DETAIL_VIDEO_SOURCE = 'video source';

    public const DETAIL_GALLERY = 'ul.item_gallery li a';

    public const DETAIL_BUY_LINKS = 'section.content_wrap ul > li > a.btn[target="_blank"]';

    public const DETAIL_WP_POST = 'link[rel="alternate"][href*="/wp-json/wp/v2/ttlpost/"]';

    public const ROW_LINKS = 'a[href]';

    public const CODE_IN_COVER_PATH = '#/package/([A-Za-z0-9]+-\d+)/#';
}
