<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiFields;
use JOOservices\CrawlerX\Adapters\AvWiki\AvWikiJson;
use JOOservices\CrawlerX\Adapters\AvWiki\Site;
use JOOservices\CrawlerX\Adapters\AvWiki\TaxonomyResolver;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;

final class Detail extends AbstractType implements TypeInterface
{
    public function __construct(private readonly CrawlHttpClient $client)
    {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $pageResponse = $this->client->get($request->url)->toPsrResponse();
        $pageHtml = $this->htmlFromResponse($pageResponse, 'AV-Wiki', 'detail');
        $response = $this->client->get(Site::detailApiUrl($request->url))->toPsrResponse();
        $payload = AvWikiJson::decode($response, 'detail');
        $posts = AvWikiJson::records($payload, 'detail');
        if (count($posts) !== 1) {
            throw new CrawlParseException('AV-Wiki detail response did not contain exactly one post.');
        }

        $post = $posts[0];
        $tags = self::embeddedTerms($post, 'post_tag');
        $resolver = new TaxonomyResolver($this->client);
        $categories = $resolver->categories([$post]);
        $item = AvWikiFields::movie($post, $tags, $categories, $pageHtml);
        $this->assertUsableMovieDetail($item, 'AV-Wiki');

        return $item;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<int|string, array<string, mixed>>
     */
    private static function embeddedTerms(array $post, string $taxonomy): array
    {
        $embedded = is_array($post['_embedded'] ?? null) ? $post['_embedded'] : [];
        $groups = is_array($embedded['wp:term'] ?? null) ? $embedded['wp:term'] : [];
        $terms = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $term) {
                $termFields = self::associativeRecord($term);
                if (($termFields['taxonomy'] ?? null) !== $taxonomy) {
                    continue;
                }
                $id = $termFields['id'] ?? null;
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    $terms[(string) $id] = $termFields;
                }
            }
        }

        return $terms;
    }

    /** @return array<string, mixed> */
    private static function associativeRecord(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $record = [];
        foreach ($value as $key => $field) {
            if (is_string($key)) {
                $record[$key] = $field;
            }
        }

        return $record;
    }
}
