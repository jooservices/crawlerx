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
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

/**
 * AV-Wiki rate-limits every request after the first (~10 s each), so a detail crawl issues exactly
 * one adapter request: the REST post with `_embed=1`, which already carries categories and tags in
 * `_embedded['wp:term']`. The article page is never requested here; its HTML is only used when the
 * orchestrator already fetched it (cover and screenshot images are not part of the REST payload).
 */
final class Detail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get(Site::detailApiUrl($request->url))->toPsrResponse();
        $posts = AvWikiJson::records(AvWikiJson::decode($response, 'detail'), 'detail');
        if (count($posts) !== 1) {
            throw new CrawlParseException('AV-Wiki detail response did not contain exactly one post.');
        }

        $post = $posts[0];
        $item = AvWikiFields::movie(
            $post,
            EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::TAG),
            EmbeddedTerms::forTaxonomy($post, EmbeddedTerms::CATEGORY),
            $this->prefetchedPage($request),
        );
        $this->assertUsableMovieDetail($item, 'AV-Wiki');

        return $item;
    }

    private function prefetchedPage(CrawlRequestDto $request): ?string
    {
        $fetch = $request->fetch;
        if ($fetch === null || $fetch->status !== 200) {
            return null;
        }

        $body = $fetch->body;
        $trimmed = ltrim($body);

        return $trimmed !== '' && ! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[') ? $body : null;
    }
}
