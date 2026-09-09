<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class PerformerIndex extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'xcity', 'performer_index');
        $crawler = new Crawler($html, $request->url);
        $urls = [];

        $crawler->filter('.itemStatus a[href*="?kana="], .avidolSrcInitial a[href*="?kana="], a[href*="?kana="]')->each(function (Crawler $node) use (&$urls, $request): void {
            $href = $node->attr('href');
            if (! is_string($href) || $href === '') {
                return;
            }

            $absolute = UriResolver::resolve($href, $request->url);
            if (! $this->hasQueryParam($absolute, 'kana')) {
                return;
            }

            $urls[$this->canonicalDiscoveryUrl($absolute, ['kana'])] = true;
        });

        $items = array_map(fn(string $url): CrawlItemResultDto => PerformerDto::item(url: $url, externalId: $url, title: $url), array_keys($urls));

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: null,
                nextUrl: null,
                hasNextPage: false,
            ),
        );
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private function canonicalDiscoveryUrl(string $url, array $allowedKeys): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $query = [];
        $queryString = is_string($parts['query'] ?? null) ? $parts['query'] : '';
        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        $filtered = [];
        foreach ($allowedKeys as $key) {
            $value = $query[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $filtered[$key] = trim($value);
            }
        }

        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/';
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return "{$scheme}://{$host}{$port}{$path}" . ($filtered !== [] ? '?' . http_build_query($filtered) : '');
    }

    private function hasQueryParam(string $url, string $key): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);
        $value = $params[$key] ?? null;

        return is_string($value) && trim($value) !== '';
    }
}
