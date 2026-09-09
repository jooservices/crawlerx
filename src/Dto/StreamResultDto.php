<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class StreamResultDto extends Dto
{
    /**
     * @param  list<string>  $segments
     */
    public function __construct(
        public readonly string $url,
        public readonly array $segments = [],
        public readonly ?string $masterPlaylist = null,
    ) {
    }
}
