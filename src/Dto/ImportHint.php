<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

final readonly class ImportHint
{
    public function __construct(
        public string $slug,
        public string $label,
        public string $action,
        public string $message,
    ) {
    }

    /**
     * @return array{slug: string, label: string, action: string}
     */
    public function toPayload(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'action' => $this->action,
        ];
    }
}
