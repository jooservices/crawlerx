<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Materiall;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parsing shared by the listing cards and the detail page.
 */
final class Fields
{
    use ParsesHtml;

    /**
     * The real address of the first lazy-loaded image under a selector: the `src` of those images is a placeholder
     * and the file is named in `data-src`.
     */
    public function lazyImage(Crawler $scope, string $selector): ?string
    {
        return $this->firstAttribute($scope, $selector, 'data-src') ?? $this->firstAttribute($scope, $selector, 'src');
    }

    /**
     * The date printed in the `data-start-date` timer of the first node matching a selector, as ISO.
     */
    public function timerDate(Crawler $scope, string $selector): ?string
    {
        return MovieMeta::isoDate($this->firstAttribute($scope, $selector, 'data-start-date'));
    }
}
