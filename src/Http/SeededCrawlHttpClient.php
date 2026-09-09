<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Http;

use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use JOOservices\CrawlerX\Dto\FetchResultDto;
use Nyholm\Psr7\Response;

final readonly class SeededCrawlHttpClient implements CrawlHttpClient
{
    public function __construct(
        private CrawlHttpClient $inner,
        private FetchResultDto $fetch,
    ) {
    }

    public function get(string $url): CrawlHttpResponse
    {
        if ($this->matches($url)) {
            $headers = ['Content-Type' => $this->contentType()];
            foreach ($this->fetch->headers as $name => $values) {
                $headers[$name] = $values[0] ?? '';
            }

            return new PsrCrawlHttpResponse(new Response(
                $this->fetch->status > 0 ? $this->fetch->status : 200,
                $headers,
                $this->fetch->body,
            ));
        }

        try {
            return $this->inner->get($url);
        } catch (NetworkConnectionException) {
            return new PsrCrawlHttpResponse(new Response(404, ['Content-Type' => 'text/plain'], ''));
        }
    }

    private function matches(string $url): bool
    {
        $finalUrl = $this->fetch->finalUrl;
        $candidates = [];
        if (is_string($finalUrl) && $finalUrl !== '') {
            $candidates[] = $finalUrl;
            $candidates[] = $this->normalize($finalUrl);
        }

        $normalized = $this->normalize($url);

        foreach ($candidates as $candidate) {
            if ($url === $candidate || $normalized === $this->normalize($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $url): string
    {
        return rtrim($url, '/');
    }

    private function contentType(): string
    {
        $trimmed = ltrim($this->fetch->body);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? 'application/json'
            : 'text/html; charset=utf-8';
    }
}
