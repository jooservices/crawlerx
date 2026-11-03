<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Shared\MakerCms;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

final class Fields
{
    use ParsesHtml;

    public function __construct(private readonly SiteConfig $config)
    {
    }

    public function text(Crawler $node, string $selector): ?string
    {
        return $this->firstText($node, $selector);
    }

    public function image(Crawler $node, string $selector): ?string
    {
        $url = $this->firstAttribute($node, $selector, 'data-src') ?? $this->firstAttribute($node, $selector, 'src');

        return $url !== null ? $this->config->absolute($url) : null;
    }

    /** @return list<array{name: string, url: string, external_id: string}> */
    public function links(Crawler $node, string $selector): array
    {
        return $node->filter($selector)->each(function (Crawler $link): array {
            $url = $this->config->absolute($link->attr('href') ?? '');

            return [
                'name' => $this->normalizeText($link->text('')) ?? '',
                'url' => $url,
                'external_id' => basename((string) parse_url($url, PHP_URL_PATH)),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    public function performers(Crawler $node): array
    {
        return array_map(static fn(array $link): array => [...$link, 'name_japanese' => $link['name']], $this->links($node, 'a[href*="' . $this->config->actressPath . '"]'));
    }

    /** @return list<string> */
    public function images(Crawler $node, string $selector): array
    {
        return array_values(array_unique(array_filter($node->filter($selector)->each(fn(Crawler $image): ?string => $this->image($image, 'img')), static fn(?string $url): bool => $url !== null)));
    }

    public function date(?string $value): ?string
    {
        if ($value === null || preg_match('/(\d{4})[年-](\d{1,2})[月-](\d{1,2})/u', $value, $match) !== 1) {
            return null;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            ? sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3]) : null;
    }

    /** @return array<string, Crawler> */
    public function rows(Crawler $node, string $selector): array
    {
        $rows = [];
        foreach ($node->filter($selector) as $element) {
            $row = new Crawler($element);
            $label = $this->text($row, '.th');
            if ($label !== null) {
                $rows[$label] = $row;
            }
        }

        return $rows;
    }
}
