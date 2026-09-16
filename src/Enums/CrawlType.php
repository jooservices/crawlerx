<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Enums;

enum CrawlType: string
{
    case Listing = 'listing';
    case Detail = 'detail';
    case Gallery = 'gallery';
    case PerformerListing = 'performer_listing';
    case PerformerDetail = 'performer_detail';
}
