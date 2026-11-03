<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiFields;
use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiJson;
use JOOservices\CrawlerX\Adapters\AvWiki\EmbeddedTerms;
use JOOservices\CrawlerX\Adapters\AvWiki\Site;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

final class Listing extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $page = max(1, $request->page);
        $response = $this->client->get(Site::listingApiUrl($request->url, $page))->toPsrResponse();
        $payload = AvWikiJson::decode($response, 'listing');
        $posts = AvWikiJson::records($payload, 'listing');
        if ($posts === []) {
            throw new CrawlParseException('AV-Wiki listing response did not contain posts.');
        }

        $items = [];
        foreach ($posts as $post) {
            $item = AvWikiFields::movie(
                $post,
                EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::TAG),
                EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::CATEGORY),
            );
            $items[$item->url] = $item;
        }

        $pageSize = Site::pageSize($request->url);
        $total = $this->headerInteger($response->getHeaderLine('X-WP-Total'));
        $totalPages = $this->headerInteger($response->getHeaderLine('X-WP-TotalPages'));
        $lastPage = $totalPages ?? ($total !== null ? (int) ceil($total / $pageSize) : null);
        $hasNextPage = $lastPage !== null ? $page < $lastPage : count($posts) === $pageSize;
        $nextPage = $hasNextPage ? $page + 1 : null;

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'movie',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: $lastPage,
                nextPage: $nextPage,
                nextUrl: $nextPage !== null ? Site::listingPageUrl($request->url, $nextPage) : null,
                hasNextPage: $hasNextPage,
            ),
        );
    }

    private function headerInteger(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }
}
