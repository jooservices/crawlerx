<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Digitalark;

use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the labelled rows, the price and the keyword links of a DIGITAL ARK item page.
 */
final class DetailFields
{
    use ParsesHtml;

    /**
     * Every `label：value` row of the product information and the purchase price lines, in page order.
     *
     * @return array<string, string>
     */
    public function labelled(Crawler $root): array
    {
        $rows = [];
        foreach ([Selectors::DETAIL_LABELLED_ROWS, Selectors::DETAIL_PURCHASE_ROWS] as $selector) {
            $root->filter($selector)->each(function (Crawler $row) use (&$rows): void {
                $parts = preg_split('/[：:]/u', (string) $this->normalizeText($row->text('')), 2);
                if (! is_array($parts) || count($parts) !== 2) {
                    return;
                }

                $label = trim($parts[0]);
                $value = trim($parts[1]);
                if ($label !== '' && $value !== '') {
                    $rows[$label] = $value;
                }
            });
        }

        return $rows;
    }

    /** @return array{amount: ?float, currency: ?string, label: ?string}|null */
    public function price(Crawler $root): ?array
    {
        $text = $this->firstText($root, Selectors::DETAIL_PRICE);
        if ($text === null || preg_match('/[￥¥]\s*([\d,]+)/u', $text, $match) !== 1) {
            return null;
        }

        return MovieMeta::price((float) str_replace(',', '', $match[1]), 'JPY', $text);
    }

    /**
     * The keyword links as the page's own script splits them: commas, 、 and ・ separate the words.
     *
     * @return list<string>
     */
    public function keywords(Crawler $root): array
    {
        $keywords = [];
        $root->filter(Selectors::DETAIL_KEYWORDS)->each(function (Crawler $node) use (&$keywords): void {
            $raw = str_replace(['、', '・'], ',', (string) $node->attr('data-keywords'));
            foreach (explode(',', $raw) as $word) {
                $word = trim($word);
                if ($word !== '') {
                    $keywords[$word] = $word;
                }
            }
        });

        return array_values($keywords);
    }
}
