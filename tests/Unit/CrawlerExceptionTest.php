<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit;

use JOOservices\CrawlerX\Exceptions\AdapterNotFoundException;
use JOOservices\CrawlerX\Exceptions\AmbiguousUrlException;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Exceptions\UnsupportedUrlException;
use JOOservices\CrawlerX\Tests\TestCase;
use JOOservices\Exceptions\Contracts\JOORuntimeExceptionInterface;

final class CrawlerExceptionTest extends TestCase
{
    public function test_crawler_exceptions_use_the_jooservices_runtime_contract(): void
    {
        $exceptions = [
            new AdapterNotFoundException('site'),
            new AmbiguousUrlException('https://example.test'),
            new CrawlBlockedException('blocked'),
            new CrawlParseException('parse failed'),
            new UnsupportedUrlException('https://example.test'),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(JOORuntimeExceptionInterface::class, $exception);
        }
    }
}
