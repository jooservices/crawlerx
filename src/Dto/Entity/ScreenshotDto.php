<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto\Entity;

use JOOservices\Dto\Attributes\MapTo;
use JOOservices\Dto\Core\Dto;

final class ScreenshotDto extends Dto
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $url,
        #[MapTo('thumbnail_url')]
        public readonly ?string $thumbnailUrl = null,
        public readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromParsed(array $data): self
    {
        $url = is_string($data['url'] ?? null) ? $data['url'] : '';
        $thumbnail = is_string($data['thumbnail_url'] ?? null) ? $data['thumbnail_url'] : null;
        unset($data['url'], $data['thumbnail_url']);

        return new self($url, $thumbnail, $data);
    }
}
