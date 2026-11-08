<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Support;

/**
 * FANZA/DMM package art: the pages show the small `…ps.jpg` thumbnail, the same CDN folder holds the large `…pl.jpg`.
 */
final class DmmCover
{
    private const THUMBNAIL_SUFFIX = '#ps(\.jpg)$#';

    public static function large(string $thumbnail): string
    {
        return (string) preg_replace(self::THUMBNAIL_SUFFIX, 'pl$1', $thumbnail);
    }
}
