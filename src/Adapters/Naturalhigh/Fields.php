<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Naturalhigh;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Fields
{
    use NormalizesUrls;
    use ParsesHtml;

    public function text(Crawler $node): ?string
    {
        return $this->normalizeText($node->text(''));
    }

    public function resolve(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url === '' ? null : $this->absolute(Site::BASE_URL . '/', $url);
    }

    /** @return array<string, Crawler> */
    public function rows(Crawler $node): array
    {
        $rows = [];
        foreach ($node->filter('tr') as $element) {
            $row = new Crawler($element);
            $label = $this->text($row->filter('th')->first());
            $value = $row->filter('td')->first();
            if ($label !== null && $value->count() > 0) {
                $rows[rtrim($label, " :：\t\n\r\0\x0B")] = $value;
            }
        }
        foreach ($node->filter('dl') as $element) {
            $list = new Crawler($element);
            foreach ($list->filter('dt') as $term) {
                $dt = new Crawler($term);
                $label = rtrim((string) $this->text($dt), " :：\t\n\r\0\x0B");
                $dd = $dt->nextAll()->filter('dd')->first();
                if ($label !== '' && $dd->count() > 0) {
                    $rows[$label] = $dd;
                }
            }
        }

        return $rows;
    }

    /** @param array<string, Crawler> $rows
     * @return array<string, string>
     */
    public function sourceFields(array $rows): array
    {
        $fields = [];
        foreach ($rows as $label => $row) {
            $value = $this->text($row);
            if ($value !== null) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    public function links(Crawler $node): array
    {
        $links = [];
        foreach ($node->filter('a[href]') as $element) {
            $link = new Crawler($element);
            $name = $this->text($link);
            $url = $this->resolve($link->attr('href'));
            if ($name !== null && $url !== null) {
                $links[] = MovieMeta::performer($name, basename((string) parse_url($url, PHP_URL_PATH)), $url);
            }
        }

        return $links;
    }

    public function date(?string $value): ?string
    {
        return MovieMeta::isoDate($value);
    }
}
