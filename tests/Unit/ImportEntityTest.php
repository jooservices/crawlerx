<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Enums\ImportEntity;
use PHPUnit\Framework\TestCase;

final class ImportEntityTest extends TestCase
{
    public function test_cases_have_human_readable_labels(): void
    {
        self::assertSame('Movie', ImportEntity::Movie->label());
        self::assertSame('Performer', ImportEntity::Performer->label());
        self::assertSame('Gallery', ImportEntity::Gallery->label());
    }
}
