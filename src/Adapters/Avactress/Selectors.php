<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avactress;

final class Selectors
{
    public const CARD = 'main section a[href]';

    public const CARD_NAME = 'h3';

    public const CARD_TEXTS = 'p';

    public const IMAGE = 'img';

    public const PAGINATION_CURRENT = 'nav[aria-label="ページネーション"] a[aria-current="page"]';

    public const PAGINATION_PAGES = 'nav[aria-label="ページネーション"] a[href]';

    public const PAGINATION_NEXT = 'nav[aria-label="ページネーション"] a[aria-label="次のページ"]';

    public const PERFORMER_NAME = 'main h1';

    public const PERFORMER_KANA = 'main h1 + p';

    public const PERFORMER_STATS = 'main div.grid-cols-4 > div';

    public const PERFORMER_ROWS = 'main dl > div';

    public const PERFORMER_FANZA = 'main a[href*="dmm.co.jp/digital/videoa/-/list/=/article=actress"]';

    public const WORKS_FILTERS = 'main section h2 + div a[href]';

    public const WORK = 'main section div.group';

    public const WORK_LINK = 'a[href]';

    public const WORK_SPANS = 'span';

    public const META_DESCRIPTION = 'meta[name="description"]';

    public const JSON_LD = 'script[type="application/ld+json"]';

    public const SCRIPTS = 'script';
}
