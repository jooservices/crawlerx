<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Plum;

use JOOservices\CrawlerX\Support\MovieMeta;

final class Cast
{
    /**
     * The site prints the cast as one "/"-separated text without links.
     *
     * @return list<array{external_id: ?string, name: string, url: ?string}>
     */
    public static function performers(?string $text): array
    {
        $names = array_filter(array_map('trim', explode('/', (string) $text)), static fn(string $name): bool => $name !== '');

        return array_values(array_map(static fn(string $name): array => MovieMeta::performer($name), array_unique($names)));
    }
}
