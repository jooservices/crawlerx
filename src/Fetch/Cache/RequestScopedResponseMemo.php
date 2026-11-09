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
        $snapshot = [
            'body' => $body,
            'headers' => self::headers($psrResponse),
            'reason' => $psrResponse->getReasonPhrase(),
            'status' => $psrResponse->getStatusCode(),
            'version' => $psrResponse->getProtocolVersion(),
        ];

        if (self::isCacheable($psrResponse, $body)) {
            $this->responses[$key] = $snapshot;
        }

        return $this->response($snapshot);
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

    public static function isCacheable(ResponseInterface $response, string $body, bool $challengeDetected = false): bool
    {
        $status = $response->getStatusCode();

        return ! $challengeDetected
            && $status >= 200
            && $status < 300
            && ! ChallengeDetector::isChallenge($body, $status, self::headers($response))
            && ! ChallengeDetector::isCaptchaChallenge($body);
    }

    /** @return array<string, list<string>> */
    private static function headers(ResponseInterface $response): array
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
