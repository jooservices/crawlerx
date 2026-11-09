<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Warashi\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Adapters\Warashi\Selectors;
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
    use ParsesUrlParts;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlResponse($response->toPsrResponse(), 'warashi', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_LINKS)
            ->each(function (Crawler $node) use (&$items, $request): void {
                $href = $node->attr('href');
                $name = $this->performerNameFromNode($node);
                if (! is_string($href) || $href === '' || $name === '') {
                    return;
                }

                $url = UriResolver::resolve($href, $request->url);
                $externalId = $this->pathMatch($url, '#/asian-female-pornstar/(\d+)/?$#i');
                if ($externalId === null) {
                    return;
                }

                $image = $this->cardImage($node, $request->url);
                $items[$url] = PerformerDto::item(
                    url: $url,
                    externalId: $externalId,
                    title: $name,
                    data: $image === null ? [] : ['profile_image_url' => $image],
                );
            });

        $currentPage = $this->currentPageNumber($crawler, $request->url);
        $nextUrl = $this->nextPageUrl($crawler, $request->url, $currentPage);
        $lastPageNum = $this->lastPageNumber($crawler);

        return new CrawlListResultDto(
            url: $request->url,
            page: $currentPage,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $currentPage,
                lastPage: $lastPageNum,
                nextPage: $nextUrl !== null ? $this->pathInteger($nextUrl, '#/page/(\d+)/?#') : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /** The portrait is the image link of the same `<figure>` as the name link. */
    private function cardImage(Crawler $name, string $baseUrl): ?string
    {
        $card = $name->closest('figure');
        $src = $card === null ? null : $this->firstAttribute($card, Selectors::PERFORMER_CARD_IMAGE, 'src');

        return $src === null ? null : UriResolver::resolve($src, $baseUrl);
    }

    private function performerNameFromNode(Crawler $node): string
    {
        $html = $node->html('');
        $text = trim(preg_replace('/\s*<br\s*\/?>\s*/i', ' ', $html) ?? '');
        $text = trim(strip_tags($text));

        return $text;
    }

    private function currentPageNumber(Crawler $crawler, string $url): int
    {
        $node = $crawler->filter(Selectors::PAGINATION_CURRENT);
        if ($node->count() > 0) {
            $text = trim($node->first()->text(''));
            if (is_numeric($text)) {
                return (int) $text;
            }
        }

        return $this->pathInteger($url, '#/page/(\d+)/?#') ?? 1;
    }

    private function nextPageUrl(Crawler $crawler, string $baseUrl, int $currentPage): ?string
    {
        $nav = $crawler->filter(Selectors::PAGINATION_NAV);
        if ($nav->count() === 0) {
            return null;
        }

        $targetPage = $currentPage + 1;
        $nextHref = null;

        $nav->first()->filter('a')->each(function (Crawler $node) use (&$nextHref, $targetPage): void {
            $href = $node->attr('href');
            if (! is_string($href) || $href === '') {
                return;
            }

            if (preg_match('#/page/' . $targetPage . '/?$#', $href) === 1) {
                $nextHref = $href;
            }
        });

        if ($nextHref === null) {
            return null;
        }

        return UriResolver::resolve($nextHref, $baseUrl);
    }

    private function lastPageNumber(Crawler $crawler): ?int
    {
        $nav = $crawler->filter(Selectors::PAGINATION_NAV);
        if ($nav->count() === 0) {
            return null;
        }

        $lastPage = null;

        $nav->first()->filter('a')->each(function (Crawler $node) use (&$lastPage): void {
            $href = $node->attr('href');
            if (! is_string($href) || $href === '') {
                return;
            }

            if (preg_match('#/page/(\d+)/?$#', $href, $match) === 1) {
                $lastPage = (int) $match[1];
            }
        });

        return $lastPage;
    }
}
