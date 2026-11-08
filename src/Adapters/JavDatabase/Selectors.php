<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDatabase;

final class Selectors
{
    public const PERFORMER_LINKS = 'a.cut-text';

    public const PAGINATION_NEXT = 'a.page-link[aria-label="Next Page"]';

    public const PAGINATION_LAST = 'a.page-link[aria-label="Last Page"]';

    public const PERFORMER_NAME = 'h1.idol-name';

    public const PERFORMER_IMAGE = '.idol-portrait img';

    public const PERFORMER_CARD_IMAGE = '.idol-thumb img';

    public const GALLERY_LINKS = 'a[data-image-src]';

    public const FAVORITE_BUTTON = 'button.simplefavorite-button';
}
