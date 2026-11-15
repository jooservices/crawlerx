<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Yuira;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The product description block. Paragraphs before the first rule are the description; the paragraphs after it are
 * `Label : value` rows (メーカー, 発売日, 品番, JAN). The product meta block adds category and tag rows.
 */
final class ProductFields
{
    use ParsesHtml;

    public function __construct(private readonly Crawler $page)
    {
    }

    public function description(): ?string
    {
        $content = $this->page->filter(Selectors::DETAIL_CONTENT);
        $paragraphs = $content->filterXPath('descendant::hr')->count() > 0
            ? $content->filterXPath('descendant::hr[1]/preceding-sibling::p')
            : $content->filter('p');
        $lines = array_filter(
            $paragraphs->each(fn(Crawler $paragraph): ?string => $this->normalizeText($paragraph->text(''))),
            static fn(?string $line): bool => $line !== null,
        );

        return $lines === [] ? null : implode("\n", $lines);
    }

    public function value(string $label): ?string
    {
        return $this->rows()[$label] ?? null;
    }

    /** @return list<string> */
    public function categories(): array
    {
        return $this->texts($this->page, Selectors::CATEGORY_LINKS);
    }

    /** @return array<string, string> every labelled row the page shows, in page order */
    public function sourceFields(): array
    {
        return [...$this->metaRows(), ...$this->rows()];
    }

    /** @return array<string, string> */
    private function rows(): array
    {
        $rows = [];
        $lines = $this->page->filter(Selectors::DETAIL_CONTENT)->filterXPath('descendant::hr[1]/following-sibling::p');
        foreach ($lines->each(fn(Crawler $line): ?string => $this->normalizeText($line->text(''))) as $line) {
            if ($line !== null && preg_match('/^([^:：]+?)\s*[:：]\s*(.+)$/u', $line, $match) === 1) {
                $rows[trim($match[1])] ??= trim($match[2]);
            }
        }

        return $rows;
    }

    /** @return array<string, string> */
    private function metaRows(): array
    {
        $rows = [];
        $this->page->filter(Selectors::META_ROW)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, Selectors::META_LABEL);
            $value = $this->firstText($row, Selectors::META_VALUE);
            if ($label !== null && $value !== null) {
                $rows[rtrim($label, " :\u{FF1A}")] = $value;
            }
        });

        return $rows;
    }
}
