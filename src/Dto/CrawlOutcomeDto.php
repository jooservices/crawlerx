<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class CrawlOutcomeDto extends Dto
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?CrawlListResultDto $list = null,
        public readonly ?CrawlItemResultDto $item = null,
        public readonly ?CrawlErrorDto $error = null,
    ) {
    }

    public static function success(CrawlListResultDto|CrawlItemResultDto $result): self
    {
        return $result instanceof CrawlListResultDto
            ? new self(ok: true, list: $result)
            : new self(ok: true, item: $result);
    }

    public static function failure(CrawlErrorDto $error): self
    {
        return new self(ok: false, error: $error);
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }
}
