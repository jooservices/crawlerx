<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Request;

use JOOservices\CrawlerX\Dto\FetchResultDto;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\Request\ReadyMarkerGate;
use PHPUnit\Framework\TestCase;

final class ReadyMarkerGateTest extends TestCase
{
    public function test_passes_through_without_markers_or_on_failures(): void
    {
        $ok = $this->fetched(true, 200, '<p>x</p>');
        $failed = $this->fetched(false, 200, '<p>x</p>');
        $redirect = $this->fetched(true, 302, '<p>x</p>');

        self::assertSame($ok, ReadyMarkerGate::apply($ok, []));
        self::assertSame($failed, ReadyMarkerGate::apply($failed, ['#x']));
        self::assertSame($redirect, ReadyMarkerGate::apply($redirect, ['#x']));
    }

    public function test_keeps_a_body_that_has_the_marker(): void
    {
        $result = $this->fetched(true, 200, '<div id="video">' . str_repeat('x', 2000) . '</div>');

        self::assertSame($result, ReadyMarkerGate::apply($result, ['#video']));
    }

    public function test_fails_a_2xx_body_without_the_marker(): void
    {
        $result = $this->fetched(true, 200, '<p>spinner</p>');

        $gated = ReadyMarkerGate::apply($result, ['#video']);

        self::assertFalse($gated->ok);
        self::assertSame('ready marker missing', $gated->error);
        self::assertSame('<p>spinner</p>', $gated->body);
        self::assertSame(200, $gated->status);
    }

    private function fetched(bool $ok, int $status, string $body): FetchResultDto
    {
        return new FetchResultDto($ok, $body, $status, FetchMethod::Http, 5, false);
    }
}
