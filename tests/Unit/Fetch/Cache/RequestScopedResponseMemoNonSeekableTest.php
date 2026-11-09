<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Unit\Fetch\Cache;

use JOOservices\CrawlerX\Fetch\Cache\RequestScopedResponseMemo;
use JOOservices\CrawlerX\Http\PsrCrawlHttpResponse;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class RequestScopedResponseMemoNonSeekableTest extends TestCase
{
    public function test_cached_first_response_keeps_a_non_seekable_body_readable(): void
    {
        $memo = new RequestScopedResponseMemo();
        $fetches = 0;
        $fetch = function () use (&$fetches): PsrCrawlHttpResponse {
            $fetches++;

            return $this->responseWithNonSeekableBody('fixture response body');
        };

        $first = $memo->fetch('https://example.test/page', [], $fetch)->toPsrResponse();
        $cached = $memo->fetch('https://example.test/page', [], $fetch)->toPsrResponse();

        self::assertSame('fixture response body', (string) $first->getBody());
        self::assertSame('fixture response body', (string) $cached->getBody());
        self::assertSame(1, $fetches);
    }

    public function test_non_cacheable_non_seekable_bodies_remain_readable(): void
    {
        $memo = new RequestScopedResponseMemo();
        $bodies = ['first failure body', 'second failure body'];
        $fetch = function () use (&$bodies): PsrCrawlHttpResponse {
            return $this->responseWithNonSeekableBody(array_shift($bodies) ?? '', 503);
        };

        $first = $memo->fetch('https://example.test/failure', [], $fetch)->toPsrResponse();
        $second = $memo->fetch('https://example.test/failure', [], $fetch)->toPsrResponse();

        self::assertSame('first failure body', (string) $first->getBody());
        self::assertSame('second failure body', (string) $second->getBody());
        self::assertSame([], $bodies);
    }

    private function responseWithNonSeekableBody(string $body, int $status = 200): PsrCrawlHttpResponse
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($sockets === false) {
            self::fail('Unable to create a socket pair for the non-seekable response stream.');
        }
        self::assertSame(strlen($body), fwrite($sockets[0], $body));
        fclose($sockets[0]);

        $stream = Stream::create($sockets[1]);
        self::assertFalse($stream->isSeekable());

        return new PsrCrawlHttpResponse(new Response($status, [], $stream));
    }
}
