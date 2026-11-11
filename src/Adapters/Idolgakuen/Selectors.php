<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Idolgakuen;

final class Selectors
{
    public const CARD = '.itemList dl';

    public const CARD_LINK = 'dl[onclick]';

    public const CARD_COVER = 'dt img[src]';

    public const CARD_PERFORMER = 'h2 .f1';

    public const CARD_TITLE = 'h2 .f2';

    public const CARD_DATE = '.itemListBox .f3';

    public const CARD_DESCRIPTION = '.itemListBox .f4';

    public const PAGINATION_CURRENT = '#page-navigation .page-numbers.current';

    public const PAGINATION_LINKS = '#page-navigation a.page-numbers[href]';

    public const PAGINATION_NEXT = '#page-navigation a.next.page-numbers[href]';

    public const DETAIL_COVER = '.item-information .box_left img[src]';

    public const DETAIL_TITLE = '.item-information .box_right .title';

    public const DETAIL_DESCRIPTION = '.item-information .box_right .f1';

    public const DETAIL_ROWS = '.item-information .box_right table tr';

    public const DETAIL_IMAGES = '.photo .photo_thumbnail_table a[href]';
}
