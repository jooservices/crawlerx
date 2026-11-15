<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

final class Selectors
{
    public const LISTING_CARDS = 'ul.products > li.product';

    public const CARD_LINK = 'li.title h2 a';

    public const CARD_IMAGE = 'img';

    public const PAGE_CURRENT = '.woocommerce-pagination .page-numbers.current';

    public const PAGE_NUMBER_LINKS = '.woocommerce-pagination a.page-numbers';

    public const PAGE_NEXT = '.woocommerce-pagination a.next.page-numbers[href]';

    public const DETAIL_TITLE = 'h2.product_title';

    public const DETAIL_CONTENT = '.elementor-widget-woocommerce-product-content .elementor-widget-container';

    public const DETAIL_PRICE = '.elementor-widget-woocommerce-product-price .woocommerce-Price-amount';

    public const GALLERY_ITEM = '.woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image';

    public const PURCHASE_LINK = '.flex01 a[href]';

    public const META_ROW = '.product_meta .detail-container';

    public const META_LABEL = '.detail-label';

    public const META_VALUE = '.detail-content';

    public const CATEGORY_LINKS = '.product_meta .posted_in a';
}
