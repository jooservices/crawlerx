<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Avactress;

use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parsing shared by the performer cards and the performer profile: the display name, the measurements and age
 * texts, the images, the pagination and the works embedded in the page's streamed (RSC) payload.
 */
final class Fields
{
    use NormalizesUrls;
    use ParsesHtml;

    private const PAREN_NAME = '#^(.+?)\s*[（(](.+)[）)]$#u';

    private const ALIAS_SEPARATOR = '#\s*[、,，/／]\s*#u';

    /** @return array{name: string, aliases: list<string>} the main name and the names shown in brackets */
    public function names(string $heading): array
    {
        if (preg_match(self::PAREN_NAME, $heading, $match) !== 1) {
            return ['name' => $heading, 'aliases' => []];
        }

        $aliases = preg_split(self::ALIAS_SEPARATOR, $match[2], -1, PREG_SPLIT_NO_EMPTY);

        return ['name' => $match[1], 'aliases' => $aliases === false ? [] : $aliases];
    }

    /**
     * The bust of a text such as "87cm (Eカップ)" or "B87 (E) / 38歳".
     *
     * @return array{bust_cm: ?int, cup: ?string}
     */
    public function bust(string $text): array
    {
        return [
            'bust_cm' => preg_match('/^B?\s*(\d{2,3})/u', $text, $match) === 1 ? (int) $match[1] : null,
            'cup' => preg_match('/[（(]\s*([A-Z])/u', $text, $match) === 1 ? $match[1] : null,
        ];
    }

    /** The age of a text such as "B88 (E) / 38歳" or "1988-05-24（38歳）". */
    public function age(?string $text): ?int
    {
        return $text !== null && preg_match('/(\d{1,3})\s*歳/u', $text, $match) === 1 ? (int) $match[1] : null;
    }

    public function centimetres(?string $text): ?int
    {
        return $text !== null && preg_match('/(\d{2,3})\s*cm/u', $text, $match) === 1 ? (int) $match[1] : null;
    }

    /** The compact measurement form the other performer adapters use, for example `B87(E) W57 H86`. */
    public function size(?int $bust, ?string $cup, ?int $waist, ?int $hip): ?string
    {
        $parts = array_filter([
            $bust === null ? null : 'B' . $bust . ($cup === null ? '' : '(' . $cup . ')'),
            $waist === null ? null : 'W' . $waist,
            $hip === null ? null : 'H' . $hip,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Labelled values of `<span>label</span><span>value</span>` tiles and `<dt>label</dt><dd>value</dd>` rows.
     *
     * @return array<string, string>
     */
    public function rows(Crawler $crawler, string $selector): array
    {
        $rows = [];
        $crawler->filter($selector)->each(function (Crawler $row) use (&$rows): void {
            $cells = $row->children();
            $label = $cells->count() > 1 ? $this->normalizeText($cells->first()->text('')) : null;
            $value = $cells->count() > 1 ? $this->normalizeText($cells->last()->text('')) : null;
            if ($label !== null && $value !== null) {
                $rows[$label] = $value;
            }
        });

        return $rows;
    }

    /**
     * One image: the large cover and its thumbnail.
     *
     * @return array<string, mixed>
     */
    public function image(string $thumbnail, ?string $caption = null, ?string $workId = null, ?string $large = null): array
    {
        return MovieMeta::filter([
            'url' => $large ?? Site::largeCover($thumbnail),
            'thumb_url' => $thumbnail,
            'caption' => $caption,
            'work_id' => $workId,
        ]);
    }

    public function src(Crawler $scope, string $selector, string $baseUrl): ?string
    {
        $value = $this->firstAttribute($scope, $selector, 'src');

        return $value === null ? null : $this->absolute($baseUrl, $value);
    }

    public function anchor(Crawler $anchor, string $baseUrl): ?string
    {
        $value = $anchor->attr('href');

        return is_string($value) && trim($value) !== '' ? $this->absolute($baseUrl, trim($value)) : null;
    }

    public function link(Crawler $scope, string $selector, string $baseUrl): ?string
    {
        $value = $this->firstAttribute($scope, $selector, 'href');

        return $value === null ? null : $this->absolute($baseUrl, $value);
    }

    /**
     * The works the page ships as JSON in its streamed script chunks, keyed by content id: they carry the large cover,
     * the list thumbnail, the sample movie and the list price that the visible markup leaves out.
     *
     * @return array<string, array<mixed>>
     */
    public function flightWorks(Crawler $crawler): array
    {
        $payload = '';
        $crawler->filter(Selectors::SCRIPTS)->each(function (Crawler $script) use (&$payload): void {
            if (preg_match('/^self\.__next_f\.push\(\[1,(".*")\]\)\s*;?$/s', trim($script->text('')), $match) === 1) {
                $chunk = json_decode($match[1], true);
                $payload .= is_string($chunk) ? $chunk : '';
            }
        });

        $start = strpos($payload, '"items":[{"content_id"');
        $items = $start === false ? null : $this->jsonArrayAt($payload, $start + strlen('"items":'));
        $works = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && is_string($item['content_id'] ?? null)) {
                $works[$item['content_id']] = $item;
            }
        }

        return $works;
    }

    /** @return array<mixed>|null the JSON array that starts at the offset */
    private function jsonArrayAt(string $text, int $offset): ?array
    {
        $depth = 0;
        $inString = false;
        for ($i = $offset, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];
            if ($inString) {
                $i += $char === '\\' ? 1 : 0;
                $inString = $char === '"' ? false : $inString;

                continue;
            }
            $inString = $char === '"';
            $depth += $char === '[' ? 1 : ($char === ']' ? -1 : 0);
            if ($depth === 0) {
                $decoded = json_decode(substr($text, $offset, $i - $offset + 1), true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /** @return array{current: int, last: int, next_url: ?string} */
    public function pagination(Crawler $crawler, string $baseUrl): array
    {
        $current = (int) ($this->firstText($crawler, Selectors::PAGINATION_CURRENT) ?? 1);
        $pages = array_map(
            static fn(string $text): int => (int) $text,
            array_filter($this->texts($crawler, Selectors::PAGINATION_PAGES), 'ctype_digit'),
        );

        return [
            'current' => max($current, 1),
            'last' => max([$current, 1, ...$pages]),
            'next_url' => $this->link($crawler, Selectors::PAGINATION_NEXT, $baseUrl),
        ];
    }
}
