<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wikidata\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Wikidata\Performers;
use JOOservices\CrawlerX\Adapters\Wikidata\Site;
use JOOservices\CrawlerX\Adapters\Wikidata\Statements;
use JOOservices\CrawlerX\Adapters\Wikidata\WikidataApi;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * One item: the entity request, then the labels of the properties and referenced items (at most 50 ids a request).
 */
final class PerformerDetail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $qid = Site::qid($request->url);
        if ($qid === null) {
            throw new CrawlParseException('Wikidata performer detail needs an item URL such as /wiki/Q22128111.');
        }

        $entity = WikidataApi::entities(WikidataApi::json($this->client, Site::entitiesUrl([$qid]), 'performer'))[$qid] ?? null;
        if ($entity === null) {
            throw new CrawlParseException("Wikidata item {$qid} does not exist.");
        }

        $labels = [];
        foreach (array_chunk(Statements::referencedIds($entity), Site::MAX_IDS) as $chunk) {
            $labels += WikidataApi::labels(WikidataApi::json($this->client, Site::labelsUrl($chunk), 'labels'));
        }

        $performer = Performers::fromEntity($entity, $labels);
        if ($performer === null) {
            throw new CrawlParseException('Wikidata performer detail did not contain the expected fields.');
        }

        return $performer->toItem((string) $performer->url);
    }
}
