<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\Catalog;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly CatalogDefinition $definition,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $fetchUrl = $this->acceptedListingUrl($request->url);
        $response = $this->client->get($fetchUrl);
        $html = $this->htmlFromResponse($response->toPsrResponse(), $this->definition->label, 'listing');
        $crawler = new Crawler($html, $fetchUrl);
        $items = [];

        $crawler->filter($this->definition->listingItemSelector)->each(function (Crawler $card) use (&$items, $fetchUrl): void {
            $link = $card->matches($this->definition->listingLinkSelector)
                ? $card
                : $card->filter($this->definition->listingLinkSelector)->first();
            if ($link->count() === 0) {
                return;
            }
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->canonicalDetailUrl($this->absolute($fetchUrl, $href));
            $externalId = $this->definition->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $title = $this->title($card, $link) ?? $externalId;
            $items[$url] = MovieDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: [
                    'code' => $externalId,
                    'cover_url' => $this->cover($card, $link, $fetchUrl),
                ],
            );
        });

        if ($items === []) {
            throw new CrawlParseException("{$this->definition->label} listing page did not contain movie cards.");
        }

        $nextUrl = $this->nextUrl($crawler, $fetchUrl);
        $nextPage = $nextUrl === null ? null : $request->page + 1;

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $nextPage,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function acceptedListingUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return $path === null || $path === '' || $path === '/'
            ? $this->definition->listingUrl
            : $url;
    }

    private function canonicalDetailUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return $url;
        }

        return $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
    }

    private function title(Crawler $card, Crawler $link): ?string
    {
        foreach ([$link->attr('title'), $this->attribute($card, 'img', 'alt'), $card->text('')] as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $title = $this->normalizeText($candidate);
            if ($title !== null) {
                return $title;
            }
        }

        return null;
    }

    private function cover(Crawler $card, Crawler $link, string $baseUrl): ?string
    {
        foreach (['data-original', 'data-src', 'src'] as $attribute) {
            $candidate = $this->attribute($card, 'img', $attribute) ?? $this->attribute($link, 'img', $attribute);
            if ($candidate !== null && ! str_contains($candidate, 'ajax-loader') && ! str_contains($candidate, '1x1.png')) {
                return $this->absolute($baseUrl, $candidate);
            }
        }

        return null;
    }

    private function attribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        $value = $node->first()->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nextUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $next = $crawler->filter('a[rel="next"], .next a[href], a.next[href]');
        if ($next->count() === 0) {
            return null;
        }

        $href = $next->first()->attr('href');

        return is_string($href) && trim($href) !== '' ? $this->absolute($baseUrl, $href) : null;
    }
}
