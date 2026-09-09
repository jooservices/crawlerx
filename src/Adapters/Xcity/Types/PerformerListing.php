<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'xcity', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter('#avidol a[href*="/idol/detail/"], #avidol a[href*="/idol/?id="], #avidol a[href*="detail/"]')
            ->each(function (Crawler $node) use (&$items, $request): void {
                $href = $node->attr('href');
                $name = $this->listingName($node);
                if (! is_string($href) || $href === '' || $name === '') {
                    return;
                }

                $url = UriResolver::resolve($href, $request->url);
                $externalId = $this->externalId($url);
                if ($externalId === null) {
                    return;
                }

                $items[$url] = PerformerDto::item(
                    url: $url,
                    externalId: $externalId,
                    title: $name,
                );
            });

        $nextUrl = $this->nextPageUrl($crawler, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $nextUrl !== null ? $this->pageNumber($nextUrl) : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function listingName(Crawler $node): string
    {
        $title = $node->attr('title');
        if (is_string($title) && trim($title) !== '') {
            return $this->cleanName($title);
        }

        $imgAlt = $node->filter('img')->count() > 0 ? $node->filter('img')->first()->attr('alt') : null;
        if (is_string($imgAlt) && trim($imgAlt) !== '') {
            return $this->cleanName($imgAlt);
        }

        return $this->cleanName($node->text(''));
    }

    private function nextPageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $nodes = $crawler->filter('.pageScrl li.next a[href*="page="], .pageScrl a[rel="next"][href*="page="]');
        if ($nodes->count() === 0) {
            return null;
        }

        $href = $nodes->first()->attr('href');
        if (! is_string($href) || $href === '') {
            return null;
        }

        return UriResolver::resolve($href, $baseUrl);
    }

    private function pageNumber(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) ? max(1, (int) $page) : null;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/idol/detail/([^/]+)/?#i', $path, $match) === 1) {
            return $match[1];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $id = $params['id'] ?? null;

            return is_string($id) && trim($id) !== '' ? trim($id) : null;
        }

        return null;
    }

    private function cleanName(string $value): string
    {
        $value = preg_replace('/\s*All Titles Information\s*$/iu', '', $value) ?? $value;

        return $this->cleanText($value);
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
