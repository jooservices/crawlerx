<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jav321\Concerns;

use JOOservices\CrawlerX\Adapters\Jav321\Selectors;
use JOOservices\CrawlerX\Adapters\Jav321\UrlNormalizer;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Shared movie-card helpers for the Jav321 listing grid and the performer filmography grid. Both render the same card:
 * a /video/ link whose text is the title followed by the site's catalogue code, an optional cover image (src or
 * data-original) and an optional download badge.
 */
trait ParsesMovieCards
{
    abstract protected function urlNormalizer(): UrlNormalizer;

    protected function movieExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#^/video/([^/]+)/?$#i', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitTitleCode(?string $text): array
    {
        if ($text === null || $text === '') {
            return [null, null];
        }

        if (preg_match('/^(.*?)\s+([a-z]+-\d+)$/u', $text, $match) === 1) {
            $title = trim($match[1]);

            return [$title === '' ? null : $title, $match[2]];
        }

        return [$text, null];
    }

    protected function cardImage(Crawler $node, string $baseUrl): ?string
    {
        if ($node->filter(Selectors::MOVIE_IMAGE)->count() === 0) {
            return null;
        }

        $image = $node->filter(Selectors::MOVIE_IMAGE)->first();
        $src = $image->attr('src') ?? $image->attr('data-original');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        return $this->cleanImageUrl($this->urlNormalizer()->absoluteAndCanonical($baseUrl, $src));
    }

    protected function cleanImageUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return preg_replace('#(?<!:)/{2,}#', '/', $url) ?? $url;
    }


    protected function hasDownloadBadge(Crawler $node): bool
    {
        return $node->filter(Selectors::DOWNLOAD_BADGE)->count() > 0;
    }
}
