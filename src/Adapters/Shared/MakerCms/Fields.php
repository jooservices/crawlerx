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

    /**
     * First usable image URL of the first matching node: lazy-load attributes first (the CMS
     * serves data-src), then src (never an inline data: placeholder), then the largest srcset
     * candidate.
     */
    public function image(Crawler $node, string $selector): ?string
    {
        foreach (['data-src', 'data-original', 'data-lazy-src', 'src'] as $attribute) {
            $url = $this->firstAttribute($node, $selector, $attribute);
            if ($url !== null && ! str_starts_with($url, 'data:')) {
                return $this->config->absolute($url);
            }
        }
        foreach (['data-srcset', 'srcset'] as $attribute) {
            $url = $this->largestSrcsetCandidate($this->firstAttribute($node, $selector, $attribute));
            if ($url !== null) {
                return $this->config->absolute($url);
            }
        }

        return null;
    }

    private function largestSrcsetCandidate(?string $srcset): ?string
    {
        $best = null;
        $bestWidth = -1.0;
        // Inline data: URIs carry their own comma; drop them (and their descriptor) before splitting.
        $srcset = preg_replace('/data:[^\s,]*,[^\s,]*(?:\s+\d+(?:\.\d+)?[wx])?\s*,?/i', '', (string) $srcset);
        foreach (explode(',', (string) $srcset) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            if ($parts === false || $parts[0] === '') {
                continue;
            }
            $width = (float) rtrim($parts[1] ?? '0', 'wx');
            if ($width > $bestWidth) {
                $best = $parts[0];
                $bestWidth = $width;
            }
        }

        return $best;
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

    /**
     * Performers linked to actress pages; sites without actress pages (plainPerformers) list
     * bare names, read from $plainSelector.
     *
     * @return list<array<string, mixed>>
     */
    public function performers(Crawler $node, string $plainSelector): array
    {
        $links = $this->links($node, 'a[href*="' . $this->config->actressPath . '"]');
        if ($links === [] && $this->config->plainPerformers) {
            $names = array_filter($node->filter($plainSelector)->each(fn(Crawler $name): ?string => $this->normalizeText($name->text(''))), static fn(?string $name): bool => $name !== null);

            return array_map(static fn(string $name): array => ['name' => $name, 'name_japanese' => $name], array_values(array_unique($names)));
        }

        return array_map(static fn(array $link): array => [...$link, 'name_japanese' => $link['name']], $links);
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
