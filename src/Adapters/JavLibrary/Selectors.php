<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary;

final class Selectors
{
    public const LISTING_VIDEOS = 'div.videos > div.video';

    public const LISTING_LINK = 'a[href*="?v="], a[href$=".html"]';

    public const LISTING_CODE = 'div.id';

    public const LISTING_TITLE = 'div.title';

    public const PAGINATION_LINKS = 'div.page_selector a.page';

    public const PAGINATION_LAST = 'div.page_selector a.page.last';

    public const DETAIL_CODE = '#video_id td.text';

    public const DETAIL_TITLE = '#video_title h3 a';

    public const DETAIL_DATE = '#video_date td.text';

    public const DETAIL_LENGTH = '#video_length span.text';

    public const DETAIL_DIRECTOR = '#video_director td.text a';

    public const DETAIL_MAKER = '#video_maker td.text a';

    public const DETAIL_LABEL = '#video_label td.text a';

    public const DETAIL_CAST = '#video_cast td.text a';

    public const DETAIL_GENRES = '#video_genres td.text a';

    public const DETAIL_COVER = 'img#video_jacket_img';

    public const DETAIL_SCREENSHOTS = '#video_preview a.previewthumbs';

    public const PERFORMER_LINKS = 'div.starbox a[href*="star_info.php"], div.starbox a[href*="vl_star.php"]';

    public const PERFORMER_NAME = 'h1.star_name, #star_name, .boxtitle';

    public const PERFORMER_IMAGE = '#star_img, img#star_img';

    public const PERFORMER_PROFILE_ROWS = '#starprofile table tr';

    public const PERFORMER_FAVORITE = '#favcount, .icn_want_count';
}
