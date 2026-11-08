<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wpb;

final class Selectors
{
    /** Main paged gravure index only (excludes sidebars and ranking lists). */
    public const LISTING_CARDS = 'ul.list-basic-a.c-grid--columns-2 > li article.item-gravure-a';

    public const CARD_LINK = 'a.item-gravure-a__container';

    public const CARD_TITLE = '.item-gravure-a__label';

    public const CARD_DATE = 'time.item-gravure-a__date';

    public const CARD_PICTURE = 'picture.item-gravure-a__pict';

    public const CARD_IMAGE = 'picture.item-gravure-a__pict img, img.c-pict__src';

    public const PAGINATION = 'ul.navi-pager-a';

    public const PAGINATION_CURRENT = 'ul.navi-pager-a [aria-current="true"] .navi-pager-a__button__label';

    public const PAGINATION_LINKS = 'ul.navi-pager-a a.navi-pager-a__button';

    public const DETAIL_TITLE = 'h1.bar-entry-a__label, h1';

    public const DETAIL_DATE = 'time.bar-entry-a__date';

    public const DETAIL_COVER = '.group-entry-a .body-gravure-a picture, .group-entry-a .body-gravure-a img';

    public const DETAIL_BODY = '.body-entry-a__document';

    public const DETAIL_TAGS = 'ul.navi-button-a--keywords > li > a.navi-button-a__button';

    public const DETAIL_BREADCRUMB = 'nav.navi-breadcrumb-a a, ol.navi-breadcrumb-a a, .navi-breadcrumb-a a';

    public const OG_TITLE = 'meta[property="og:title"]';

    public const OG_DESCRIPTION = 'meta[property="og:description"]';

    public const OG_IMAGE = 'meta[property="og:image"]';
}
