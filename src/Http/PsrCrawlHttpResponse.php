<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use Psr\Http\Message\ResponseInterface;

final readonly class PsrCrawlHttpResponse implements CrawlHttpResponse
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function toPsrResponse(): ResponseInterface
    {
        return $this->response;
    }
}
