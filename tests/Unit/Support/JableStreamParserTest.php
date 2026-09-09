<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Support;

use DateTimeImmutable;
use JOOservices\CrawlerX\Support\JableStreamParser;
use JOOservices\CrawlerX\Tests\TestCase;

final class JableStreamParserTest extends TestCase
{
    public function test_parse_extracts_manifest_video_id_poster_and_expiry(): void
    {
        $html = $this->loadFixture('jable/detail-fjin-091.html');

        $parsed = JableStreamParser::parse($html);

        self::assertNotNull($parsed);
        self::assertStringContainsString('.m3u8', $parsed['manifest_url']);
        self::assertSame('52422', $parsed['video_id']);
        self::assertStringContainsString('preview.jpg', (string) $parsed['poster_url']);
        self::assertNotNull($parsed['expires_at']);
    }

    public function test_parse_returns_null_when_manifest_missing(): void
    {
        self::assertNull(JableStreamParser::parse('<html><body></body></html>'));
    }

    public function test_expires_at_from_manifest_url_reads_unix_segment(): void
    {
        $expiresAt = JableStreamParser::expiresAtFromManifestUrl(
            'https://cdn.example.test/hls/token/1781563113/59000/59868/59868.m3u8',
        );

        self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
        self::assertSame(1781563113, $expiresAt?->getTimestamp());
    }
}
