<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavDatabase\Types;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesUrlParts;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavDatabase\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use ParsesUrlParts;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    protected function htmlFromResponse(ResponseInterface $response, string $site, string $context): string
    {
        $html = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        $isChallenge = str_contains($html, 'cf-browser-verification')
            || str_contains($html, 'cf-mitigated')
            || str_contains($html, 'Just a moment...')
            || str_contains($html, 'Attention Required!');

        if ($isChallenge) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        if (trim($html) === '') {
            throw new \RuntimeException("{$site} {$context} page is empty.");
        }

        return $html;
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'javdatabase', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(Selectors::PERFORMER_LINKS)
            ->each(function (Crawler $node) use (&$items, $request): void {
                $href = $node->attr('href');
                $name = trim($node->text(''));
                if (! is_string($href) || $href === '' || $name === '') {
                    return;
                }

                $url = UriResolver::resolve($href, $request->url);
                $externalId = $this->externalId($url);
                if ($externalId === null) {
                    return;
                }

                $thumbnail = $this->cardThumbnail($node, $request->url);
                $items[$url] = PerformerDto::item(
                    url: $url,
                    externalId: $externalId,
                    title: $name,
                    data: $thumbnail === null ? [] : ['profile_image_url' => $thumbnail],
                );
            });

        $nextUrl = $this->nextPageUrl($crawler, $request->url);
        $lastPageNum = $this->lastPageNumber($crawler);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: $lastPageNum,
                nextPage: $nextUrl !== null ? $this->pageNumber($nextUrl) : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /** The portrait sits in `.idol-thumb` of the same card as the name link. */
    private function cardThumbnail(Crawler $nameLink, string $baseUrl): ?string
    {
        $card = $nameLink->closest('.card');
        $src = $card === null ? null : $this->firstAttribute($card, Selectors::PERFORMER_CARD_IMAGE, 'src');

        return $src === null ? null : UriResolver::resolve($src, $baseUrl);
    }

    private function nextPageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PAGINATION_NEXT);
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if (! is_string($href) || $href === '') {
            return null;
        }

        return UriResolver::resolve($href, $baseUrl);
    }

    private function lastPageNumber(Crawler $crawler): ?int
    {
        $node = $crawler->filter(Selectors::PAGINATION_LAST);
        if ($node->count() === 0) {
            return null;
        }

        $href = $node->first()->attr('href');
        if (! is_string($href) || $href === '') {
            return null;
        }

        if (preg_match('#/page/(\d+)/#', $href, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function pageNumber(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/page/(\d+)/?#', $path, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function externalId(string $url): ?string
    {
        return $this->pathMatch($url, '#/idols/([^/]+)/?#i');
    }
}
