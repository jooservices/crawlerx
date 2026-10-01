<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavPhotos\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavPhotos\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class Listing extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'javphotos', 'listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::LISTING_GALLERIES)->each(function (Crawler $card) use (&$items, $request): void {
            $href = $card->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $title = $this->cardTitle($card) ?? $externalId;
            $items[$url] = GalleryDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: [
                    'metadata' => [
                        'thumbnail_url' => $this->cardThumbnail($card, $request->url),
                    ],
                ],
            )->withNextCrawlType('gallery');
        });

        if ($items === []) {
            throw new CrawlParseException('JavPhotos listing page did not contain gallery cards.');
        }

        $nextUrl = $this->nextUrl($crawler, $request->url);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'gallery',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: null,
                nextPage: $nextUrl === null ? null : $this->pageNumber($nextUrl),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function cardTitle(Crawler $card): ?string
    {
        foreach ([$card->attr('title'), $this->attribute($card, 'img', 'alt'), $this->text($card, 'p')] as $candidate) {
            $title = is_string($candidate) ? $this->normalizeText($candidate) : null;
            if ($title !== null) {
                return $title;
            }
        }

        return null;
    }

    private function cardThumbnail(Crawler $card, string $baseUrl): ?string
    {
        $candidate = $this->attribute($card, 'img', 'src');
        if ($candidate === null) {
            return null;
        }

        return $this->absolute($baseUrl, $candidate);
    }

    private function nextUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PAGINATION_NEXT);
        $next = null;
        $node->each(function (Crawler $link) use (&$next): void {
            if (strtolower(trim($link->text(''))) !== 'next') {
                return;
            }

            $href = $link->attr('href');
            if (is_string($href) && trim($href) !== '') {
                $next = $href;
            }
        });

        return $next === null ? null : $this->absolute($baseUrl, $next);
    }

    private function pageNumber(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/free/(\d+)/?$#i', $path, $match) === 1) {
            return max(1, (int) $match[1]);
        }

        return null;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/free/([^/]+)/?$#i', $path, $match) === 1) {
            $slug = $match[1];

            return ! is_numeric($slug) ? $slug : null;
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

    private function text(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        return $node->first()->text('');
    }
}
