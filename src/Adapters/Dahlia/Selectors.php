<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia;

final class Selectors
{
    public const CARD = '.new-works-wrapper';

    public const CARD_LINK = '.new-works-img-box a[href]';

    public const CARD_COVER = '.new-works-img-box img';

    public const CARD_TITLE = '.new-works-title a';

    public const CARD_CAST = '.new-works-actress div';

    public const CARD_PURCHASE_LINKS = '.new-works-btn-box .view_timer a[href]:not(.pop_sample)';

    public const CARD_RELEASE_NOTES = '.new-works-btn-box .view_timer[data-end-date] > div';

    public const SAMPLE_VIDEO = 'a.pop_sample';

    public const PAGINATION_CURRENT = '.wp-pagenavi span.current';

    public const PAGINATION_NEXT = '.wp-pagenavi a.nextpostslink';

    public const PAGINATION_LAST = '.wp-pagenavi a.last';

    public const PAGINATION_PAGES = '.wp-pagenavi a.page';

    public const DETAIL_TITLE = '.bar02_works h1';

    public const DETAIL_COVER = '.box_works01_img a.pop_sample > img, .box_works01_img_ie img';

    public const DETAIL_SAMPLE_VIDEO = '.box_works01_img a.pop_sample';

    public const DETAIL_GALLERY = '.box_works01_ga a.pop_img';

    public const DETAIL_DESCRIPTION = '.box_works01_text p';

    public const DETAIL_ROWS = '.box_works01_list li';

    public const DETAIL_PURCHASE_LINKS = '.img_bottom_sp .view_timer a[href], #bottom-bar .view_timer a[href]';

    public const DETAIL_RELEASE_NOTES = '.img_bottom_sp .view_timer[data-end-date] > div';

    public const PERFORMER_CARD = 'li.actress-box';

    public const PERFORMER_CARD_LINK = '.img_actress01 a[href]';

    public const PERFORMER_CARD_PHOTO = '.img_actress01 img';

    public const PERFORMER_CARD_NAME = '.text_name';

    public const PERFORMER_CARD_ROMAJI = '.text_name span';

    public const PERFORMER_NAME = '.bar02_category h1';

    public const PERFORMER_ROMAJI = '.bar02_category h1 span';

    public const PERFORMER_PHOTO = '.box_actress02_left img';

    public const PERFORMER_ROWS = '.box_actress02_list li';

    public const PERFORMER_WORK = '.box_actress03';

    public const PERFORMER_WORK_LINK = '.box_actress03_right .text_name a[href]';

    public const PERFORMER_WORK_COVER = '.box_actress03_left img';

    public const PERFORMER_WORK_ROWS = '.text_kanren01';

    public const PERFORMER_WORK_PURCHASE_LINKS = '.view_timer a[href]:not(.pop_sample)';

    public const PERFORMER_WORK_RELEASE_NOTES = '.view_timer[data-end-date] > div';
}
