<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Dto;

use JOOservices\Dto\Core\Dto;

final class FixtureSampleDto extends Dto
{
    /**
     * @param  list<self>  $followUps  extra pages the sample links to (for example the date-listing page a
     *                                 date index points at); shared tests register them as responders
     */
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly string $type = 'listing',
        public readonly array $followUps = [],
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

        $followUps = [];
        $rawFollowUps = $data['followUps'] ?? [];
        if (is_array($rawFollowUps)) {
            foreach ($rawFollowUps as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                /** @var array<string, mixed> $entry */
                $followUp = self::fromManifestEntry($entry);
                if ($followUp instanceof self) {
                    $followUps[] = $followUp;
                }
            }
        }

        return new self(
            url: trim($url),
            name: trim($name),
            type: is_string($type) && $type !== '' ? $type : 'listing',
            followUps: $followUps,
        );
    }
}
