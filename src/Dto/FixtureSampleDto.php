<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class FixtureSampleDto extends Dto
{
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly string $type = 'listing',
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromManifestEntry(array $data): ?self
    {
        $url = $data['url'] ?? null;
        $name = $data['name'] ?? null;

        if (! is_string($url) || trim($url) === '' || ! is_string($name) || trim($name) === '') {
            return null;
        }

        $type = $data['type'] ?? 'listing';

        return new self(
            url: trim($url),
            name: trim($name),
            type: is_string($type) && $type !== '' ? $type : 'listing',
        );
    }
}
