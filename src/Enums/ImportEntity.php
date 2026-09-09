<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Enums;

enum ImportEntity: string
{
    case Movie = 'movie';
    case Performer = 'performer';

    public function label(): string
    {
        return match ($this) {
            self::Movie => 'Movie',
            self::Performer => 'Performer',
        };
    }
}
