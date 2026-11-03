<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Adapters\AvWiki;

use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiJson;
use JOOservices\CrawlerX\Exceptions\CrawlBlockedException;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AvWikiJsonTest extends TestCase
{
    public function test_decodes_public_json_response(): void
    {
        self::assertSame(['id' => 123], AvWikiJson::decode(new Response(200, [], '{"id":123}'), 'detail'));
    }

    public function test_rejects_empty_and_error_responses_as_blocked(): void
    {
        try {
            AvWikiJson::decode(new Response(200, [], '  '), 'listing');
            self::fail('Expected an empty response to be treated as blocked.');
        } catch (CrawlBlockedException) {
            self::assertTrue(true);
        }

        $this->expectException(CrawlBlockedException::class);
        AvWikiJson::decode(new Response(403, [], '{"message":"forbidden"}'), 'listing');
    }

    public function test_rejects_invalid_json(): void
    {
        $this->expectException(CrawlParseException::class);
        AvWikiJson::decode(new Response(200, [], '{invalid'), 'listing');
    }

    public function test_extracts_records_from_lists_and_single_objects(): void
    {
        self::assertSame([['id' => 1]], AvWikiJson::records([['id' => 1], 'skip'], 'listing'));
        self::assertSame([['id' => 2]], AvWikiJson::records(['id' => 2], 'detail'));
        self::assertSame([], AvWikiJson::records([], 'listing'));
    }
}
