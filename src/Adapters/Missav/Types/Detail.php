<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Missav\Selectors;
use JOOservices\CrawlerX\Adapters\Missav\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractType implements TypeInterface
{
    use InteractsWithMissavHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'MissAV', 'detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE) ?? $externalId;
        $coverUrl = $this->firstAttribute($crawler, Selectors::DETAIL_COVER, 'src');

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: [
                'code' => CodeNormalizer::canonical($title, $externalId, $request->url),
                'cover_url' => $coverUrl === null ? null : $this->normalizer->absolute($request->url, $coverUrl),
                'description' => $this->firstText($crawler, Selectors::DETAIL_DESCRIPTION),
                'date' => $this->releaseDate($crawler),
                'duration' => $this->durationMinutes($crawler),
                'performers' => $this->texts($crawler, Selectors::DETAIL_PERFORMERS),
                'tags' => $this->texts($crawler, Selectors::DETAIL_TAGS),
            ],
        );

        $this->assertUsableDetail($item, 'MissAV');

        return $item;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim(basename($path), '/') : null;
    }

    private function releaseDate(Crawler $crawler): ?string
    {
        if ($crawler->filter(Selectors::DETAIL_DATE)->count() === 0) {
            return null;
        }

        $node = $crawler->filter(Selectors::DETAIL_DATE)->first();
        $value = trim($node->attr('content') ?? $node->attr('datetime') ?? $node->attr('data-release-date') ?? $node->text(''));
        if ($value === '') {
            return null;
        }

        preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $value, $matches);

        return isset($matches[0]) ? str_replace('/', '-', $matches[0]) : trim($value);
    }

    private function durationMinutes(Crawler $crawler): ?int
    {
        if ($crawler->filter(Selectors::DETAIL_DURATION)->count() === 0) {
            return null;
        }

        $node = $crawler->filter(Selectors::DETAIL_DURATION)->first();
        $value = trim($node->attr('content') ?? $node->attr('data-duration') ?? $node->text(''));
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(1, (int) round(((float) $value) / 60));
        }

        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $value, $matches) === 1) {
            return max(1, ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0));
        }

        if (preg_match('/(?:(\d+)\s*h(?:ours?)?)?\s*(?:(\d+)\s*m(?:in(?:utes?)?)?)?/i', $value, $matches) === 1 && (($matches[1] ?? '') !== '' || ($matches[2] ?? '') !== '')) {
            return max(1, ((int) ($matches[1] ?? 0) * 60) + (int) ($matches[2] ?? 0));
        }

        if (preg_match('/(\d{1,3})\s*min/i', $value, $matches) === 1) {
            return max(1, (int) $matches[1]);
        }

        return null;
    }
}
