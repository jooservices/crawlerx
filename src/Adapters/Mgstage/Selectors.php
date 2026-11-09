<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Mgstage;

final class Selectors
{
    public const CARD = 'li.product_list_item';

    public const CARD_LINK = 'a[href*="/product/product_detail/"]';

    public const CARD_TITLE = 'a.title';

    public const CARD_COVER = 'h5 img';

    public const CARD_ACTORS = 'p.actor a';

    public const CARD_REVIEW = 'p.review';

    public const CARD_WISH = 'p.wish';

    public const CARD_ORIGIN_PRICE = '.origin_price';

    public const CARD_MIN_PRICE = '.min-price';

    public const CARD_DISCOUNT = '.discount-rate-label';

    public const CARD_SALE_END = '.sale_remaining_time';

    public const CARD_SAMPLE_PLAYER = 'a.button_sample';

    public const CARD_SAMPLE_IMAGES = '.sample_image_btn a[href]';

    public const CARD_BADGES = '.title span[class*="btn_"]';

    public const SEARCH_TEXT = '.search_form .search_text';

    public const PAGER_CURRENT = '.pager_search_bottom strong.page';

    public const PAGER_LINKS = '.pager_search_bottom a.page';

    public const DETAIL_TITLE = 'h1.tag';

    public const DETAIL_BADGES = '.common_detail_cover > span[class*="btn_"]';

    public const DETAIL_COVER = '.detail_photo h2 img, .detail_data h2 img';

    public const DETAIL_COVER_LARGE = 'a#EnlargeImage';

    public const DETAIL_SAMPLE_PLAYER = '.sample_movie_btn a.button_sample';

    public const DETAIL_ROWS = '.detail_data table tr';

    public const DETAIL_COUNTERS = '.detail_data tr, .detail_data dl';

    public const DETAIL_DESCRIPTION = 'p.introduction';

    public const DETAIL_SCREENSHOTS = 'dl#sample-photo a.sample_image';

    public const DETAIL_PRICE_OPTIONS = '.price-list label.price-option';

    public const DETAIL_SALE_END = '#sale-countdown';

    public const DETAIL_REVIEWS = '.user_review li';

    public const DETAIL_OG_DESCRIPTION = 'meta[property="og:description"]';

    public const DETAIL_OG_IMAGE = 'meta[property="og:image"]';
}
