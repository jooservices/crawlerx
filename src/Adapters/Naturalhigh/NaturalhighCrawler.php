<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Naturalhigh;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Adapters\Concerns\DetectsUrls;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Types\Detail;
use JOOservices\CrawlerX\Adapters\Naturalhigh\Types\Listing;
use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\ListingCapable;
use JOOservices\CrawlerX\Contracts\UrlDetectCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlOptionsDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\HttpOptionsDto;

final class NaturalhighCrawler extends AbstractBaseCrawler implements DetailCapable, ListingCapable, UrlDetectCapable
{
    use DetectsUrls;
    use UrlDetectRules;

    public function name(): string
    {
        return Site::SLUG;
    }

    public function prepareRequest(CrawlRequestDto $request): void
    {
        $http = $request->options->http ?? new HttpOptionsDto();
        $headers = [];
        foreach (is_array($this->options['headers'] ?? null) ? $this->options['headers'] : [] as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }
        foreach ($http->headers ?? [] as $name => $value) {
            $header = $this->header($headers, $name);
            $headers[$header ?? $name] = $value;
        }
        $cookieHeader = $this->header($headers, 'Cookie');
        $headers[$cookieHeader ?? 'Cookie'] = $this->ageGateCookie($cookieHeader === null ? null : $headers[$cookieHeader]);
        $options = $request->options ?? new CrawlOptionsDto();
        $requestWithHeaders = new CrawlRequestDto(
            url: $request->url,
            type: $request->type,
            page: $request->page,
            options: new CrawlOptionsDto(
                http: new HttpOptionsDto($http->timeout, $http->verifySsl, $headers, $http->proxy),
                fetch: $options->fetch,
                methodTimeoutSeconds: $options->methodTimeoutSeconds,
                storageState: $options->storageState,
                readyMarkers: $options->readyMarkers,
            ),
            fetch: $request->fetch,
            query: $request->query,
        );

        parent::prepareRequest($requestWithHeaders);
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        return (new Listing($this->client))->execute($request);
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return (new Detail($this->client))->execute($request);
    }

    /** @param array<string, string> $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach (array_keys($headers) as $header) {
            if (strcasecmp($header, $name) === 0) {
                return $header;
            }
        }

        return null;
    }

    private function ageGateCookie(?string $cookie): string
    {
        $cookies = array_filter(
            array_map('trim', explode(';', $cookie ?? '')),
            static fn(string $value): bool => $value !== '',
        );
        $cookies = array_values(array_filter(
            $cookies,
            static fn(string $value): bool => preg_match('/^age_gate\s*=/i', $value) !== 1,
        ));
        $cookies[] = 'age_gate=18';

        return implode('; ', $cookies);
    }
}
