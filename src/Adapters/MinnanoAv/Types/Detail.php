<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Selectors;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Support\CodeNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class Detail extends AbstractHtmlType implements TypeInterface
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'minnanoav';
    }

    protected function contextLabel(): string
    {
        return 'av_detail';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $crawler = $this->fetchCrawler($request);
        $externalId = $this->externalId($request->url);
        $title = $this->firstText($crawler, Selectors::AV_DETAIL_TITLE) ?? $externalId ?? '';
        $code = CodeNormalizer::canonical($title, $externalId, $request->url);

        $item = MovieDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $title,
            data: array_filter([
                'code' => $code,
                'title' => $title !== '' ? $title : null,
            ], fn(mixed $value): bool => $value !== null && $value !== ''),
        );

        $this->assertUsableFilmographyDetail($item);

        return $item;
    }

    private function assertUsableFilmographyDetail(CrawlItemResultDto $item): void
    {
        $movie = $item->meta['movie'] ?? [];
        $title = is_array($movie) ? ($movie['title'] ?? null) : null;
        $hasTitle = is_string($title) && trim($title) !== '';
        $code = is_array($movie) ? ($movie['code'] ?? null) : null;
        $hasCode = is_string($code) && trim($code) !== '';

        if (! $hasTitle || ! $hasCode) {
            throw new \RuntimeException('minnanoav detail page did not contain expected movie fields.');
        }
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(av\d+)\.html$#i', $path, $match) === 1) {
            return strtolower($match[1]);
        }

        return null;
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        if ($node->count() === 0) {
            return null;
        }

        return $this->normalizeText($node->first()->text(''));
    }
}
