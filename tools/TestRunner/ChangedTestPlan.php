<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\TestRunner;

final readonly class ChangedTestPlan
{
    /**
     * @param list<string> $testPaths
     * @param list<string> $fixtureSlugs
     */
    public function __construct(
        public bool $fullSuite,
        public array $testPaths,
        public array $fixtureSlugs,
        public ?string $reason = null,
    ) {
    }

    public function fixtureFilter(): ?string
    {
        if ($this->fixtureSlugs === []) {
            return null;
        }

        $slugs = array_map(static fn(string $slug): string => preg_quote($slug, '~'), $this->fixtureSlugs);

        return '~(?:' . implode('|', $slugs) . ')~';
    }
}
