<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Aisex;

final class Selectors
{
    public const PERFORMER_CARDS = 'a.actress-card-link[href*="/actress/"]';

    public const PERFORMER_NAME = '.actress-card-name';

    public const PAGINATION_NAV = 'nav.pagination';

    public const PAGE_CURRENT = 'span.current';

    public const PROFILE_NAME = '.profile-name';

    public const PROFILE_RUBY = '.profile-ruby';

    public const PROFILE_IMAGE = '.profile-image[src]';

    public const SPEC_ITEMS = '.spec-item';

    public const SPEC_LABEL = '.spec-label';

    public const SPEC_VALUE = '.spec-value';
}
