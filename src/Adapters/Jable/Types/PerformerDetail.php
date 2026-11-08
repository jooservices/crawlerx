<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Jable\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Jable\Selectors;
use JOOservices\CrawlerX\Adapters\Jable\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'Jable', 'performer_detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->externalId($request->url);
        $name = $this->name($crawler);

        if ($externalId === null) {
            throw new CrawlParseException('Jable performer detail URL does not carry a model slug.');
        }

        if ($name === null) {
            throw new CrawlParseException('Jable performer detail page did not contain a model name.');
        }

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: [
                'name' => $name,
                'profile_image_url' => $this->imageUrl($crawler, $request->url),
                'raw_profile' => MovieMeta::filter(['videos' => $this->firstText($crawler, '.title-box span.inactive-color')]),
            ],
        );

        return $item;
    }

    private function name(Crawler $crawler): ?string
    {
        foreach (['.title-box h2.mb-1', '.title-box h2', 'h1'] as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() === 0) {
                continue;
            }

            $text = $this->normalizeText($nodes->first()->text(''));
            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        return null;
    }

    /** The model's own photo; a page without one gives null (a video screenshot or the avatar placeholder is not it). */
    private function imageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::MODEL_PHOTO, 'data-src') ?? $this->firstAttribute($crawler, Selectors::MODEL_PHOTO, 'src');

        return $src === null ? null : $this->normalizer->absolute($baseUrl, $src);
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
