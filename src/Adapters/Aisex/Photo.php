<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Aisex;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use Symfony\Component\DomCrawler\Crawler;

/** The actress photo of a card or profile, without the site's own "no image" placeholder. */
final class Photo
{
    use NormalizesUrls;

    private const PLACEHOLDER = '/assets/images/no-image';

    public function url(Crawler $node, string $baseUrl): ?string
    {
        $image = $node->filter('img[src]');
        if ($image->count() === 0) {
            return null;
        }

        $src = trim((string) $image->first()->attr('src'));
        if ($src === '' || str_contains($src, self::PLACEHOLDER)) {
            return null;
        }

        return $this->absolute($baseUrl, $src);
    }
}
