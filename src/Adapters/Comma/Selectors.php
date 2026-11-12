<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Comma;

final class Selectors
{
    public const LISTING_CARDS = 'ul.flex-md-wrap > li';

    public const CARD_LINK = 'a[href*="/dvd/"]';

    public const CARD_CODE = 'span.badge';

    public const CARD_TITLE = 'p.card-text';

    public const CARD_IMAGE = 'img';

    public const DETAIL_ROOT = 'section.inner.pt-5';

    public const DETAIL_TITLE = 'h1 .flex-grow-1';

    public const DETAIL_ROWS = 'ul > li.d-flex';

    public const ROW_LABEL = 'h2';

    public const ROW_VALUE = 'div';

    public const ROW_LINKS = 'a';

    public const DETAIL_COVER = 'div.col-md-5 img';

    public const DETAIL_SPECIAL_SITE = 'a.btn-warning[href]';

    public const GALLERY_ITEMS = './/h3[contains(., "ギャラリー")]/following-sibling::ul[1]/li';
}
