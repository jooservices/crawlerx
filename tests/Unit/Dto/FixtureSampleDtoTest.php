<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Dto;

use JOOservices\CrawlerX\Dto\FixtureSampleDto;
use JOOservices\CrawlerX\Tests\TestCase;

final class FixtureSampleDtoTest extends TestCase
{
    public function test_follow_ups_are_parsed_and_invalid_entries_skipped(): void
    {
        $sample = FixtureSampleDto::fromManifestEntry([
            'url' => 'https://example.test/works/date',
            'name' => 'dates.html',
            'followUps' => [
                ['url' => 'https://example.test/works/list/date/2026-12-01', 'name' => 'list-2026-12-01.html'],
                ['url' => '', 'name' => 'broken.html'],
                'not-an-entry',
            ],
        ]);

        self::assertInstanceOf(FixtureSampleDto::class, $sample);
        self::assertCount(1, $sample->followUps);
        self::assertSame('list-2026-12-01.html', $sample->followUps[0]->name);
    }

    public function test_follow_ups_default_to_empty(): void
    {
        $sample = FixtureSampleDto::fromManifestEntry(['url' => 'https://example.test/a', 'name' => 'a.html', 'followUps' => 'x']);

        self::assertInstanceOf(FixtureSampleDto::class, $sample);
        self::assertSame([], $sample->followUps);
    }
}
