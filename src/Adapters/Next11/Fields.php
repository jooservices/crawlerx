<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Next11;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads the labelled rows (dt/dd pairs) and links shared by the Next11 listing cards and detail pages.
 *
 * The pages declare `<base href="/">`, so every relative URL is resolved against the site root.
 */
final class Fields
{
    use NormalizesUrls;
    use ParsesHtml;

    public const BASE_URL = 'https://next11.co.jp/';

    public function resolve(?string $url): ?string
    {
        $url = $url === null ? '' : trim($url);

        return $url === '' ? null : $this->absolute(self::BASE_URL, $url);
    }

    public function text(Crawler $node): ?string
    {
        return $this->normalizeText($node->text(''));
    }

    /**
     * @return array<string, Crawler> the dd node of every dt row, keyed by the label without its trailing colon
     */
    public function rows(Crawler $list): array
    {
        $rows = [];
        foreach ($list->filter('dt') as $term) {
            $label = preg_replace('/[：: ]+$/u', '', (string) $this->normalizeText($term->textContent)) ?? '';
            $value = (new Crawler($term))->nextAll()->first();
            if ($label !== '' && $value->count() > 0 && $value->nodeName() === 'dd') {
                $rows[$label] = $value;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, Crawler>  $rows
     * @return array<string, string> label => value as displayed (links joined with ", ")
     */
    public function sourceFields(array $rows): array
    {
        $fields = [];
        foreach ($rows as $label => $row) {
            $names = array_column($this->links($row), 'name');
            $value = $names !== [] ? implode(', ', $names) : $this->text($row);
            if ($value !== null) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    public function links(Crawler $row): array
    {
        $links = [];
        foreach ($row->filter('a[href]') as $element) {
            $link = new Crawler($element);
            $name = $this->text($link);
            if ($name !== null) {
                $url = $this->resolve($link->attr('href'));
                $links[] = MovieMeta::performer($name, $this->categoryId($url), $url);
            }
        }

        return $links;
    }

    /** @return array{external_id: ?string, name: string, url: ?string}|null the first linked entity, or the plain text */
    public function entity(Crawler $row): ?array
    {
        $links = $this->links($row);
        if ($links !== []) {
            return $links[0];
        }
        $text = $this->text($row);

        return $text === null ? null : MovieMeta::performer($text);
    }

    public function productId(?string $url): ?string
    {
        return $this->queryValue($url, 'product_id');
    }

    public function categoryId(?string $url): ?string
    {
        return $this->queryValue($url, 'category_id');
    }

    private function queryValue(?string $url, string $key): ?string
    {
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $value = $query[$key] ?? null;

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? $value : null;
    }
}
