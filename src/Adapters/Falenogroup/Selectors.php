<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Falenogroup;

final class Selectors
{
    public const CARD = '.waku_kanren01';

    public const CARD_LINK = 'a[href]';

    public const CARD_TITLE = '.text_name a';

    public const CARD_COVER = 'a > img';

    public const CARD_MAKER_LOGO = 'p > img';

    public const SAMPLE_VIDEO = 'a.pop_sample';

    public const MAKER_NAME = '.bar02 h1';

    public const MAKER_LOGO = '.box_actress02_left img';

    public const PAGINATION_CURRENT = '.wp-pagenavi span.current';

    public const PAGINATION_NEXT = '.wp-pagenavi a.nextpostslink';

    public const PAGINATION_LAST = '.wp-pagenavi a.last';

    public const PAGINATION_PAGES = '.wp-pagenavi a.page';

    public const DETAIL_TITLE = '.bar02 h1';

    public const DETAIL_COVER = '.box_works01_img a.pop_sample > img';

    public const DETAIL_SAMPLE_VIDEO = '.box_works01_img a.pop_sample';

    public const DETAIL_GALLERY = '.box_works01_ga a.pop_img';

    public const DETAIL_DESCRIPTION = '.box_works01_text p';

    public const DETAIL_ROWS = '.box_works01_list li';

    public const DETAIL_PURCHASE_LINKS = '.img_bottom_sp .view_timer a[href], #bottom-bar .view_timer a[href]';

    public const DETAIL_RELEASE_NOTE = '.img_bottom_sp .view_timer[data-end-date] > div';

    public const CARD_PURCHASE_LINKS = '.view_timer a[href]:not(.pop_sample)';

    public const CARD_RELEASE_NOTE = '.view_timer[data-end-date] > div';
}
