<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Gravidia;

final class Selectors
{
    public const LISTING_CARDS = '.tl-ProductListCol4_Item';

    public const CARD_LINK = 'a[href^="https://gravidia.jp/product/"], a[href^="/product/"]';

    public const CARD_TITLE = '.tl-ProductListCol4_Title';

    public const CARD_MODEL = '.tl-ProductListCol4_SubTitle';

    public const CARD_PHOTOGRAPHER = '.tl-ProductListCol4_MetaItem';

    public const CARD_LABEL = '.tl-ProductListCol4_TagText';

    public const CARD_IMAGE = '.tl-ProductListCol4_Img img';

    public const PAGINATION_LINKS = '.st-Pagination_Link';

    public const PAGINATION_ACTIVE = '.st-Pagination_Link-active';

    public const DETAIL_ROOT = '.p-ProductDetail';

    public const DETAIL_TITLE = '.p-ProductDetail_Title';

    public const DETAIL_MODEL = '.p-ProductDetail_ModelLink a';

    public const DETAIL_DESCRIPTION = '.p-ProductDetail_Profile';

    public const DETAIL_COVER = '.p-ProductDetail_Img img';

    public const DETAIL_ROWS = '.p-ProductDetail_MetaItem';

    public const DETAIL_TAGS = '.p-ProductDetail_Tags .p-ProductDetail_TagText';

    public const DETAIL_PRICE = '.p-ProductDetail_PriceText';

    public const DETAIL_PREVIEW = '.js-productTameshiyomi[data-tameshiyomi-url]';
}
