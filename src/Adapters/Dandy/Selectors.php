<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dandy;

final class Selectors
{
    public const CARDS = '#top_latest .works_list > li';

    public const CARD_LINK = 'a[href]';

    public const CARD_IMAGE = 'figure img';

    public const CARD_TITLE = 'h3';

    public const CARD_DATES = '.date';

    public const LIST_HEADING = '#top_latest h2.common .ja';

    public const LIST_COUNT = '#top_latest h2.common .sub';

    public const PAGINATION = 'nav.pagination';

    public const PAGINATION_CURRENT = 'nav.pagination .pages .current';

    public const PAGINATION_PAGES = 'nav.pagination .pages a';

    public const PAGINATION_NEXT = 'nav.pagination a.next-page';

    public const PAGINATION_LAST = 'nav.pagination a.last-page';

    public const TITLE = '#product h1';

    public const DESCRIPTION = '#product .description';

    public const ROWS = '#product dl.data > div';

    public const ROW_LABEL = 'dt';

    public const ROW_VALUE = 'dd';

    public const GALLERY_IMAGES = '#kv .swiper-slide img.main';

    public const SAMPLE_VIDEO = '#product .samplemovie video source';

    public const STORE_LINKS = '#product .store li a';

    public const STORE_LOGO = '.logo img';

    public const STORE_LABEL = '.label';

    public const STORE_NOTE = '#product .subarea .note';

    public const LABEL_LOGO = '#maker_list li.current img';

    public const OG_IMAGE = 'meta[property="og:image"]';
}
