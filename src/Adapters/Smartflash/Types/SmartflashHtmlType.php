<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Smartflash\Types;

use JOOservices\CrawlerX\Adapters\AbstractHtmlType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

abstract class SmartflashHtmlType extends AbstractHtmlType
{
    use ParsesHtml;

    protected function siteLabel(): string
    {
        return 'smartflash';
    }

    /** @return list<string> */
    protected function hrefs(Crawler $crawler, string $selector, string $baseUrl): array
    {
        return array_values(array_filter($crawler->filter($selector)->each(
            fn(Crawler $node): string => $this->absolute($baseUrl, (string) $node->attr('href')),
        ), static fn(string $href): bool => $href !== ''));
    }
}
