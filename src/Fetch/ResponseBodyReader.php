<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch;

use Psr\Http\Message\StreamInterface;

final readonly class ResponseBodyReader
{
    private const READ_CHUNK_BYTES = 8192;

    public function __construct(private int $maximumBytes)
    {
    }

    public function read(StreamInterface $stream): string
    {
        $size = $stream->getSize();
        if ($size !== null && $size > $this->maximumBytes) {
            throw new ResponseBodyTooLargeException($this->maximumBytes);
        }

        $position = $stream->isSeekable() ? $stream->tell() : null;
        if ($position !== null) {
            $stream->rewind();
        }

        $contents = '';
        try {
            while (! $stream->eof()) {
                $remaining = $this->maximumBytes - strlen($contents);
                $chunk = $stream->read(min(self::READ_CHUNK_BYTES, $remaining + 1));
                if ($chunk === '') {
                    break;
                }

                if (strlen($chunk) > $remaining) {
                    throw new ResponseBodyTooLargeException($this->maximumBytes);
                }

                $contents .= $chunk;
            }
        } finally {
            if ($position !== null) {
                $stream->seek($position);
            }
        }

        return $contents;
    }
}
