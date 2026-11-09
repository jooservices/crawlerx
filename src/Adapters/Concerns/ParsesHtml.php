<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Concerns;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

trait ParsesHtml
{
    protected function htmlResponse(ResponseInterface $response, string $site, string $context): string
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

    protected function firstText(Crawler $crawler, string $selector): ?string
    {
        if ($crawler->filter($selector)->count() === 0) {
            return null;
        }

        return $this->normalizeText($crawler->filter($selector)->first()->text(''));
    }

    protected function firstAttribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        if ($crawler->filter($selector)->count() === 0) {
            return null;
        }

        $value = $crawler->filter($selector)->first()->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    protected function texts(Crawler $crawler, string $selector): array
    {
        if ($crawler->filter($selector)->count() === 0) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $crawler->filter($selector)->each(fn(Crawler $node): string => $this->normalizeText($node->text('')) ?? ''),
            fn(string $text): bool => $text !== '',
        )));
    }

    protected function normalizeText(string $text): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', html_entity_decode($text));
        $normalized = trim(is_string($normalized) ? $normalized : '');

        return $normalized !== '' ? $normalized : null;
    }
}
