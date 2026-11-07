<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Dahlia;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Falenogroup\Fields as StoreButtons;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parsing shared by the work listing cards, the work detail page and the performer pages: labelled rows, cast
 * names, images, the time-gated store buttons and their dated notes.
 */
final class Fields
{
    use NormalizesUrls;
    use ParsesHtml;

    private const DATED_NOTE = '#^\d{4}/\d{1,2}/\d{1,2}\s#';

    private const CAST_SEPARATOR = '#\s*[、,，/／]\s*#u';

    /**
     * Labelled rows (`<span>label</span><p>value</p>`). The page repeats a row for each time-gated variant, so the
     * first non-empty value of a label wins.
     *
     * @return array<string, string>
     */
    public function rows(Crawler $scope, string $selector): array
    {
        $rows = [];
        $scope->filter($selector)->each(function (Crawler $row) use (&$rows): void {
            $label = $this->firstText($row, 'span');
            if ($label === null) {
                return;
            }

            $rows[$label] = ($rows[$label] ?? '') !== '' ? $rows[$label] : ($this->firstText($row, 'p') ?? '');
        });

        return array_filter($rows, static fn(string $value): bool => $value !== '');
    }

    /** @return list<array{external_id: ?string, name: string, url: ?string}> */
    public function cast(?string $names): array
    {
        $parts = preg_split(self::CAST_SEPARATOR, (string) $names, -1, PREG_SPLIT_NO_EMPTY);

        return array_map(static fn(string $name): array => MovieMeta::performer($name), $parts === false ? [] : $parts);
    }

    /** The absolute, original-quality URL of an image attribute. */
    public function image(Crawler $scope, string $selector, string $baseUrl, string $attribute = 'src'): ?string
    {
        $value = $this->firstAttribute($scope, $selector, $attribute);

        return $value === null ? null : Site::originalImage($this->absolute($baseUrl, $value));
    }

    public function link(Crawler $scope, string $selector, string $baseUrl, string $attribute = 'href'): ?string
    {
        $value = $this->firstAttribute($scope, $selector, $attribute);

        return $value === null ? null : $this->absolute($baseUrl, $value);
    }

    /**
     * Store buttons that carry a link (FANZA, H-NEXT), with the date each goes live.
     *
     * @return list<array{store: string, label: ?string, url: string, available_from: ?string}>
     */
    public function purchaseLinks(Crawler $scope, string $selector): array
    {
        return (new StoreButtons())->purchaseLinks($scope, $selector);
    }

    /**
     * The dated texts of the buttons that are not live yet, for example "2026/11/1 予約開始" or "2026/11/14 配信開始".
     *
     * @return list<string>
     */
    public function releaseNotes(Crawler $scope, string $selector): array
    {
        return array_values(array_unique(array_filter(
            $this->texts($scope, $selector),
            static fn(string $text): bool => preg_match(self::DATED_NOTE, $text) === 1,
        )));
    }
}
