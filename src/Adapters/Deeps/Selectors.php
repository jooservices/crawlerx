<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Deeps;

final class Selectors
{
    public const LISTING_CARDS = '.main_column .product_list_wrap ul.list_box > li';

    public const LISTING_TOTAL = '.main_column .product_list_wrap h1 span';

    public const PAGE_LINKS = '.main_column p.footbango a.navbango';

    public const DETAIL_TITLE = '.main_column#item h1';

    public const DETAIL_COVER = '.main_column#item figure.jacket img';

    public const DETAIL_ROWS = '.main_column#item .inner table tr';

    public const SAMPLE_VIDEO = '.main_column#item #sample_movie iframe[src]';

    public const PURCHASE_LINKS = '.main_column#item #sample_movie ul.link_list a[href]';

    public const SAMPLE_IMAGES = '.main_column#item ul.sample_img li';

    public const DESCRIPTION = '.main_column#item .item_content';
}
