<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Materiall;

final class Selectors
{
    public const CARD = '.waku_kanren01';

    public const CARD_LINK = '.anime2 > a[href]';

    public const CARD_COVER = '.anime2 > a > img';

    public const CARD_TITLE = '.caption_title > div:first-child';

    public const CARD_CAST = '.caption_title > div:nth-child(2)';

    public const CARD_PURCHASE_LINKS = '.bottom_kanren01 .view_timer a[href]';

    public const CARD_RESERVATION = '.bottom_kanren01 .view_timer:not(.vc_none)[data-start-date]';

    public const CARD_DISTRIBUTION = '.bottom_kanren01 .view_timer.vc_none[data-start-date]';

    public const PAGINATION_CURRENT = '.wp-pagenavi span.current';

    public const PAGINATION_NEXT = '.wp-pagenavi a.nextpostslink';

    public const PAGINATION_LAST = '.wp-pagenavi a.last';

    public const PAGINATION_PAGES = '.wp-pagenavi a.page';

    public const DETAIL_OG_TITLE = 'meta[property="og:title"]';

    public const DETAIL_TITLE = '.bar02_works h1';

    public const DETAIL_COVER = '.box_works01_img > img, .box_works01_img > a.pop_sample > img';

    public const DETAIL_SAMPLE_VIDEO = '.box_works01_img a.pop_sample';

    public const DETAIL_DESCRIPTION = '.box_works01_text p';

    public const DETAIL_ROWS = '.box_works01_list li';

    public const DETAIL_PURCHASE_LINKS = '.img_bottom .view_timer a[href]';
}
