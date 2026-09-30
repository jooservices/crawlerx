<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Dto\ImportHint;
use PHPUnit\Framework\TestCase;

final class ImportHintTest extends TestCase
{
    public function test_payload_excludes_display_message(): void
    {
        $hint = new ImportHint('onejav', 'OneJAV', 'crawl_movie', 'Movie import available.');

        self::assertSame([
            'slug' => 'onejav',
            'label' => 'OneJAV',
            'action' => 'crawl_movie',
        ], $hint->toPayload());
    }
}
