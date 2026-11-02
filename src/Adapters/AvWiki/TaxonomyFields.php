<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\AvWiki;

use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class TaxonomyFields
{
    /** @param array<string, mixed> $term
     *  @return array<string, mixed>
     */
    public static function performer(array $term): array
    {
        $description = is_string($term['description'] ?? null) ? $term['description'] : '';
        $fields = self::descriptionFields($description);
        $rawName = $fields['AV女優名'] ?? (is_string($term['name'] ?? null) ? $term['name'] : '');
        [$name, $reading, $romanized] = self::nameParts($rawName);
        $profileImage = self::profileImage($description);
        $birthDateSource = $fields['生年月日'] ?? null;
        $birthDate = MovieMeta::isoDate(is_string($birthDateSource) ? $birthDateSource : null);
        $height = self::height(is_string($fields['サイズ'] ?? null) ? $fields['サイズ'] : null);
        $aliasesSource = is_string($fields['別名義'] ?? null) ? trim($fields['別名義']) : null;
        $aliases = self::aliases($aliasesSource);

        return MovieMeta::filter([
            'external_id' => self::stringId($term['id'] ?? null),
            'name' => $name,
            'name_japanese' => $name,
            'name_kana' => $reading,
            'url' => is_string($term['link'] ?? null) ? $term['link'] : null,
            'profile_image_url' => $profileImage,
            'birth_date_raw' => $birthDate,
            'height_raw' => $height,
            'size_raw' => $fields['サイズ'] ?? null,
            'aliases' => $aliases,
            'raw_profile' => $fields,
            'metadata' => [
                'taxonomy' => 'post_tag',
                'name_reading' => $reading,
                'name_romanized' => $romanized,
                'birth_date' => $birthDate,
                'birth_date_source' => $birthDateSource,
                'aliases_source' => $aliasesSource,
                'sns' => self::socialLinks($description),
                'wordpress' => $term,
            ],
        ]);
    }

    /** @return array<string, string> */
    public static function descriptionFields(string $html): array
    {
        $crawler = new Crawler('<div>' . $html . '</div>', Site::BASE_URL);
        $fields = [];
        $crawler->filter('dl')->each(static function (Crawler $list) use (&$fields): void {
            $list->filter('dt')->each(static function (Crawler $labelNode) use (&$fields): void {
                $label = rtrim(self::text($labelNode->text('')), '：:');
                $value = self::followingSiblingText($labelNode, 'dd');
                if ($label !== '' && $value !== '') {
                    $fields[$label] = $value;
                }
            });
        });

        return $fields;
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private static function nameParts(string $value): array
    {
        $name = $value;
        $reading = null;
        $romanized = null;
        if (preg_match('/^(.*?)(?:[（(]([^）)]+)[）)])?(?:\s*-\s*(.+))?$/u', trim($value), $matches) === 1) {
            $name = trim($matches[1]);
            $reading = isset($matches[2]) && trim($matches[2]) !== '' ? trim($matches[2]) : null;
            $romanized = isset($matches[3]) && trim($matches[3]) !== '' ? trim($matches[3]) : null;
        }

        return [$name, $reading, $romanized];
    }

    private static function profileImage(string $html): ?string
    {
        $crawler = new Crawler('<div>' . $html . '</div>', Site::BASE_URL);
        $src = $crawler->filter('img[src]')->count() > 0 ? $crawler->filter('img[src]')->first()->attr('src') : null;

        return is_string($src) && trim($src) !== '' ? Site::absoluteUrl($src) : null;
    }

    private static function height(?string $size): ?string
    {
        if ($size !== null && preg_match('/(?:^|\s)(T\s*\d{2,3})\b/u', $size, $matches) === 1) {
            return strtoupper(str_replace(' ', '', $matches[1]));
        }

        return null;
    }

    /** @return list<string> */
    private static function aliases(?string $source): array
    {
        if ($source === null || preg_match('/^(?:[-ー―\s]+|なし|無し|該当なし)$/u', trim($source)) === 1) {
            return [];
        }

        $parts = preg_split('/[、,／\/]+/u', $source);
        if ($parts === false) {
            return [];
        }

        $aliases = array_map('trim', $parts);

        return array_values(array_filter($aliases, static fn(string $alias): bool => $alias !== ''));
    }

    /** @return list<array{label: string, text: string, url: string}> */
    private static function socialLinks(string $html): array
    {
        $crawler = new Crawler('<div>' . $html . '</div>', Site::BASE_URL);
        $links = [];
        $crawler->filter('dl')->each(static function (Crawler $list) use (&$links): void {
            $list->filter('dt')->each(static function (Crawler $labelNode) use (&$links): void {
                if (! str_contains(self::text($labelNode->text('')), 'SNS')) {
                    return;
                }

                $valueNode = $labelNode->getNode(0);
                $sibling = $valueNode?->nextSibling;
                while ($sibling !== null && ! $sibling instanceof \DOMElement) {
                    $sibling = $sibling->nextSibling;
                }
                if (! $sibling instanceof \DOMElement || strtolower($sibling->tagName) !== 'dd') {
                    return;
                }
                $valueCrawler = new Crawler($sibling);
                $valueCrawler->filter('a[href]')->each(static function (Crawler $link) use (&$links): void {
                    $url = $link->attr('href');
                    if (! is_string($url) || trim($url) === '') {
                        return;
                    }
                    $links[] = [
                        'label' => self::text($link->text('')),
                        'text' => self::text($link->attr('title') ?? $link->text('')),
                        'url' => $url,
                    ];
                });
            });
        });

        return $links;
    }

    private static function stringId(mixed $id): ?string
    {
        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (string) $id : null;
    }

    private static function followingSiblingText(Crawler $node, string $tag): string
    {
        $element = $node->getNode(0);
        $sibling = $element?->nextSibling;
        while ($sibling !== null) {
            if ($sibling instanceof \DOMElement) {
                return strtolower($sibling->tagName) === $tag ? self::text($sibling->textContent) : '';
            }
            $sibling = $sibling->nextSibling;
        }

        return '';
    }

    private static function text(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $decoded);

        return trim(is_string($normalized) ? $normalized : $decoded);
    }
}
