<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Cache;

use JOOservices\CrawlerX\Contracts\CrawlHttpResponse;
use JOOservices\CrawlerX\Enums\FetchMethod;
use JOOservices\CrawlerX\Fetch\ChallengeDetector;
use JOOservices\CrawlerX\Http\PsrCrawlHttpResponse;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class RequestScopedResponseMemo
{
    /**
     * @var array<string, array{
     *     body: string,
     *     headers: array<string, list<string>>,
     *     reason: string,
     *     status: int,
     *     version: string
     * }>
     */
    private array $responses = [];

    /**
     * @param array<string, string> $headers
     * @param callable(): CrawlHttpResponse $fetch
     */
    public function fetch(string $url, array $headers, callable $fetch): CrawlHttpResponse
    {
        $key = ResponseCacheKey::for(FetchMethod::Http, $url, $headers);
        if (isset($this->responses[$key])) {
            return $this->response($this->responses[$key]);
        }

        $response = $fetch();
        $psrResponse = $response->toPsrResponse();
        $body = (string) $psrResponse->getBody();

        if ($this->isCacheable($psrResponse, $body)) {
            $this->responses[$key] = [
                'body' => $body,
                'headers' => $this->headers($psrResponse),
                'reason' => $psrResponse->getReasonPhrase(),
                'status' => $psrResponse->getStatusCode(),
                'version' => $psrResponse->getProtocolVersion(),
            ];
        }

        return $response;
    }

    /**
     * @param array{
     *     body: string,
     *     headers: array<string, list<string>>,
     *     reason: string,
     *     status: int,
     *     version: string
     * } $entry
     */
    private function response(array $entry): CrawlHttpResponse
    {
        return new PsrCrawlHttpResponse(new Response(
            $entry['status'],
            $entry['headers'],
            $entry['body'],
            $entry['version'],
            $entry['reason'],
        ));
    }

    private function isCacheable(ResponseInterface $response, string $body): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200
            && $status < 300
            && ! ChallengeDetector::isChallenge($body, $status, $this->headers($response))
            && preg_match('/\b(?:captcha|recaptcha|hcaptcha|funcaptcha)\b/i', $body) !== 1;
    }

    /** @return array<string, list<string>> */
    private function headers(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (is_string($name)) {
                $headers[$name] = array_values($values);
            }
        }

        return $headers;
    }
}
