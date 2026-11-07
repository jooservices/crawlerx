<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Bomb;

/** One image of a pack as the page declares it; `alt` is kept raw because an ideographic space separates its name parts. */
final readonly class PackImage
{
    public function __construct(
        public string $id,
        public string $url,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $alt = null,
    ) {
    }
}
