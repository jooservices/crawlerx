<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Rocket;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

final class Fields
{
    use ParsesHtml;

    public function text(Crawler $node, string $selector): ?string
    {
        return $this->firstText($node, $selector);
    }

    /** @return list<array{name: string, url: string, external_id: string}> */
    public function links(Crawler $node, string $selector = 'a[href]'): array
    {
        $links = [];
        foreach ($node->filter($selector) as $element) {
            $link = new Crawler($element);
            $url = Site::absolute($link->attr('href') ?? '');
            $links[] = ['name' => $this->normalizeText($link->text('')) ?? '', 'url' => $url, 'external_id' => Site::slug($url)];
        }

        return $links;
    }

    /** @return list<array{external_id: string, name: string, name_japanese: string, url: string}> */
    public function performers(Crawler $node): array
    {
        return array_map(
            static fn(array $link): array => [...$link, 'name_japanese' => $link['name']],
            $this->links($node),
        );
    }

    public function image(Crawler $node, string $selector): ?string
    {
        $url = $this->firstAttribute($node, $selector, 'src');

        return $url !== null ? Site::absolute($url) : null;
    }

    /** @return list<string> */
    public function images(Crawler $node, string $selector): array
    {
        $urls = [];
        foreach ($node->filter($selector) as $element) {
            $src = (new Crawler($element))->attr('src');
            if (is_string($src) && trim($src) !== '') {
                $urls[] = Site::absolute(trim($src));
            }
        }

        return $urls;
    }

    /** @return array<string, Crawler> label => value cell of the product info table */
    public function rows(Crawler $node): array
    {
        $rows = [];
        foreach ($node->filter('.product-info tr') as $element) {
            $row = new Crawler($element);
            $label = $this->text($row, 'th');
            if ($label !== null && $row->filter('td')->count() > 0) {
                $rows[$label] = $row->filter('td')->first();
            }
        }

        return $rows;
    }

    /** @param array<string, Crawler> $rows the text of the first of the labelled rows that exists */
    public function value(array $rows, string ...$labels): ?string
    {
        foreach ($labels as $label) {
            if (isset($rows[$label])) {
                return $this->normalizeText($rows[$label]->text(''));
            }
        }

        return null;
    }

    /** @param array<string, Crawler> $rows
     * @return array<string, string>
     */
    public function sourceFields(array $rows): array
    {
        return array_filter(
            array_map(fn(Crawler $cell): string => $this->normalizeText($cell->text('')) ?? '', $rows),
            static fn(string $value): bool => $value !== '',
        );
    }
}
