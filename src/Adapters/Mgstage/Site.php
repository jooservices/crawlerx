<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Mgstage;

final class Site
{
    public const BASE_URL = 'https://www.mgstage.com';

    public const CURRENCY = 'JPY';

    /** The store is in Japan time; its dates carry no zone. */
    public const UTC_OFFSET = '+09:00';

    /** Star ratings are out of five. */
    public const RATING_SCALE = 5.0;
}
