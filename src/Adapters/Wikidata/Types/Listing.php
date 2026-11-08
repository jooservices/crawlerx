<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Wikidata\Performers;
use JOOservices\CrawlerX\Adapters\Wikidata\Site;
use JOOservices\CrawlerX\Adapters\Wikidata\WikidataApi;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * One page of the Japanese pornographic film actors: a SPARQL request for the ordered Q-ids, then one wbgetentities
 * request for those items (two requests a page). The page is an OFFSET/LIMIT window of the query in the URL.
 */
final class Listing extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        if (! Site::isListing($request->url)) {
            throw new CrawlParseException('Wikidata listing needs a Query Service URL (' . Site::SPARQL_URL . ').');
        }

        $offset = Site::offset($request->url);
        $limit = min(Site::limit($request->url), Site::MAX_IDS);
        $ids = WikidataApi::listedIds(WikidataApi::json($this->client, $request->url, 'listing'));
        $entities = $ids === []
            ? []
            : WikidataApi::entities(WikidataApi::json($this->client, Site::entitiesUrl($ids), 'listing items'));

        $items = [];
        foreach ($ids as $id) {
            $item = $this->item($entities[$id] ?? null);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $hasNext = count($ids) >= $limit;
        $page = intdiv($offset, $limit) + 1;

        return new CrawlListResultDto(
            url: $request->url,
            page: $page,
            entityType: 'performer',
            items: $items,
            pagination: new CrawlPaginationDto(
                currentPage: $page,
                lastPage: null,
                nextPage: $hasNext ? $page + 1 : null,
                nextUrl: $hasNext ? Site::listingUrl($offset + $limit, $limit) : null,
                hasNextPage: $hasNext,
            ),
        );
    }

    /**
     * @param  array<string, mixed>|null  $entity
     */
    private function item(?array $entity): ?CrawlItemResultDto
    {
        $performer = $entity === null ? null : Performers::fromEntity($entity);

        return $performer?->toItem((string) $performer->url);
    }
}
