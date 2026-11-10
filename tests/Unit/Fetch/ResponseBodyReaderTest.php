<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch;

use JOOservices\CrawlerX\Fetch\ResponseBodyReader;
use JOOservices\CrawlerX\Fetch\ResponseBodyTooLargeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ResponseBodyReaderTest extends TestCase
{
    public function test_reads_body_within_limit_and_preserves_seekable_cursor(): void
    {
        $stream = (new Psr17Factory())->createStream('body');
        $stream->seek(2);

        self::assertSame('body', (new ResponseBodyReader(4))->read($stream));
        self::assertSame(2, $stream->tell());
    }

    public function test_rejects_body_larger_than_the_configured_limit(): void
    {
        $stream = (new Psr17Factory())->createStream('large body');

        $this->expectException(ResponseBodyTooLargeException::class);
        (new ResponseBodyReader(4))->read($stream);
    }
}
