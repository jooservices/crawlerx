<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    private const CARD_LINK = '.horizontal-img-box a[href*="/models/"]';

    private const CARD_TITLE = '.detail .title';

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Jable', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = [];

        $crawler->filter(self::CARD_LINK)->each(function (Crawler $link) use (&$items, $request): void {
            $href = $link->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $url = $this->absolute($request->url, $href);
            $externalId = $this->externalId($url);
            if ($externalId === null) {
                return;
            }

            $name = $link->filter(self::CARD_TITLE)->count() > 0
                ? $this->normalizeText($link->filter(self::CARD_TITLE)->first()->text(''))
                : $this->normalizeText($link->text(''));

            if ($name === null) {
                return;
            }

            $items[$url] = PerformerDto::item(
                url: $url,
                externalId: $externalId,
                title: $name,
            )->withNextCrawlType('performer_detail');
        });

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: $this->pagination($crawler, $request->url, $request->page),
        );
    }

    private function pagination(Crawler $crawler, string $url, int $currentPage): CrawlPaginationDto
    {
        $nextUrl = null;

        $crawler->filter('ul.pagination a.page-link')->each(function (Crawler $node) use (&$nextUrl, $url): void {
            $label = strtolower(trim($node->text('')));
            if (! in_array($label, ['next', '»'], true)) {
                return;
            }

            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $nextUrl = $this->absolute($url, $href);
        });

        return new CrawlPaginationDto(
            currentPage: $currentPage,
            lastPage: null,
            nextPage: $nextUrl !== null ? $currentPage + 1 : null,
            nextUrl: $nextUrl,
            hasNextPage: $nextUrl !== null,
        );
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || preg_match('#/models/([^/]+)/?#i', $path, $match) !== 1) {
            return null;
        }

        $slug = trim($match[1], '/');

        return $slug !== '' ? $slug : null;
    }
}
