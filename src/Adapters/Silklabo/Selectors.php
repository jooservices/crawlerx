<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Silklabo;

final class Selectors
{
    public const CARD = '#c-main-cnt .c-product-list > figure';

    public const CARD_LINK = 'a[href*="/detail/"]';

    public const CARD_COVER = 'img';

    public const CARD_TITLE = '.comment';

    public const PAGINATION_CURRENT = '.pagination .active a';

    public const PAGINATION_LINKS = '.pagination a[href]';

    public const DETAIL_COVER_LINK = '.detail-info .img-area a[href]';

    public const DETAIL_COVER = '.detail-info .img-area img[src]';

    public const DETAIL_TEXT = '.detail-info .text-area';

    public const DETAIL_GALLERY = '#photoArea1 a[rel^="lightbox"]';
}
