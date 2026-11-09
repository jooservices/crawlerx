<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Aisex\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Aisex\Photo;
use JOOservices\CrawlerX\Adapters\Aisex\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use ParsesUrlParts;

    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'aisex', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_CARDS)->each(function (Crawler $node) use (&$items, $request): void {
            $href = $node->attr('href');
            $title = $this->firstText($node, Selectors::PERFORMER_NAME);
            if (! is_string($href) || $href === '' || $title === null) {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->pathMatch($url, '#^/actress/(\\d+)/?$#');
            if ($externalId === null || isset($items[$url])) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $title,
                data: ['profile_image_url' => (new Photo())->url($node, $request->url)],
            );
        });

        if ($items === []) {
            throw new CrawlParseException('Aisex listing page did not contain performer cards.');
        }

        $currentPage = $this->currentPage($crawler) ?? $this->queryNumber($request->url, 'page') ?? 1;
        $nextUrl = $this->nextUrl($crawler, $request->url, $currentPage);
        $lastPage = $this->lastPage($crawler);

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastPage,
                nextPage: $nextUrl === null ? null : $this->queryNumber($nextUrl, 'page'),
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    private function currentPage(Crawler $crawler): ?int
    {
        $nav = $crawler->filter(Selectors::PAGINATION_NAV);
        if ($nav->count() === 0) {
            return null;
        }

        $node = $nav->first()->filter(Selectors::PAGE_CURRENT);
        if ($node->count() === 0) {
            return null;
        }

        $text = trim($node->first()->text(''));
        if (is_numeric($text)) {
            return (int) $text;
        }

        return null;
    }

    private function nextUrl(Crawler $crawler, string $baseUrl, int $currentPage): ?string
    {
        $nav = $crawler->filter(Selectors::PAGINATION_NAV);
        if ($nav->count() === 0) {
            return null;
        }

        $target = $currentPage + 1;
        $href = null;

        $nav->first()->filter('a[href*="?page="]')->each(function (Crawler $node) use (&$href, $target): void {
            $candidate = $node->attr('href');
            if (! is_string($candidate) || $candidate === '') {
                return;
            }

            if (preg_match('/[?&]page=' . $target . '$/', $candidate) === 1) {
                $href = $candidate;
            }
        });

        if ($href === null) {
            return null;
        }

        return $this->absolute($baseUrl, $href);
    }

    private function lastPage(Crawler $crawler): ?int
    {
        $nav = $crawler->filter(Selectors::PAGINATION_NAV);
        if ($nav->count() === 0) {
            return null;
        }

        $last = null;

        $nav->first()->filter('a[href*="?page="]')->each(function (Crawler $node) use (&$last): void {
            $href = $node->attr('href');
            if (is_string($href) && preg_match('/[?&]page=(\d+)$/', $href, $match) === 1) {
                $page = (int) $match[1];
                if ($last === null || $page > $last) {
                    $last = $page;
                }
            }
        });

        return $last;
    }
}
