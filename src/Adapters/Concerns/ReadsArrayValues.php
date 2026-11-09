<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

/**
 * Reads nested arrays out of decoded JSON.
 */
trait ReadsArrayValues
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        /** @var array<string, mixed> $array */
        $array = is_array($value) ? $value : [];

        return $array;
    }
}
