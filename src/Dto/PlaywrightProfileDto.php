<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class PlaywrightProfileDto extends Dto
{
    /**
     * @param  array{width: int, height: int}  $viewport
     */
    public function __construct(
        public readonly string $browser = 'chromium',
        public readonly bool $headless = true,
        public readonly int $postWaitMs = 8000,
        public readonly int $navigationTimeoutMs = 90000,
        public readonly bool $stealthEnabled = true,
        public readonly array $viewport = ['width' => 1440, 'height' => 900],
        public readonly string $locale = 'en-US',
        public readonly ?string $timezoneId = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $storageStatePath = null,
    ) {
    }
}
