<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Missav\Types;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

trait InteractsWithMissavHtml
{
    use ParsesHtml;

    protected function firstText(Crawler $crawler, string $selector): ?string
    {
        if ($crawler->filter($selector)->count() === 0) {
            return null;
        }

        $node = $crawler->filter($selector)->first();
        $content = $node->attr('content');

        return $this->normalizeText(is_string($content) && $content !== '' ? $content : $node->text(''));
    }

    protected function firstAttribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        if ($crawler->filter($selector)->count() === 0) {
            return null;
        }

        $node = $crawler->filter($selector)->first();
        $value = $node->attr('content') ?? $node->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
