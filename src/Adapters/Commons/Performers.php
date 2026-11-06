<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Commons;

use JOOservices\CrawlerX\Dto\Entity\PerformerDto;

/**
 * Builds the person entity (an idol's category) from a Commons category page.
 */
final class Performers
{
    private const TOOL_HOSTS = ['toolforge.org', 'wmflabs.org', 'wmcloud.org', 'wikimedia.org', 'wikidata.org'];

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $member  category-member row (sortkey prefix, timestamp) when listed
     */
    public static function fromPage(array $page, array $member = []): ?PerformerDto
    {
        $title = is_string($page['title'] ?? null) ? $page['title'] : null;
        if ($title === null || ! is_int($page['pageid'] ?? null) || ! Site::isCategory($title)) {
            return null;
        }

        $name = substr($title, strlen('Category:'));
        $props = CommonsApi::assoc($page['pageprops'] ?? []);
        $wikitext = self::wikitext($page);
        $languages = self::languageTitles($page);
        $original = CommonsApi::assoc($page['original'] ?? []);
        $thumbnail = CommonsApi::assoc($page['thumbnail'] ?? []);
        $imageFile = is_string($page['pageimage'] ?? null) ? $page['pageimage'] : null;
        $image = is_string($original['source'] ?? null) ? Site::cleanMediaUrl($original['source']) : null;
        $wikidata = is_string($props['wikibase_item'] ?? null) ? $props['wikibase_item'] : null;
        $info = CommonsApi::assoc($page['categoryinfo'] ?? []);
        $sortkey = $props['defaultsort'] ?? ($member['sortkeyprefix'] ?? null);
        $aliases = array_values(array_unique(array_filter(
            $languages,
            static fn(string $alias): bool => $alias !== $name,
        )));

        return new PerformerDto(
            externalId: (string) $page['pageid'],
            name: $name,
            nameJapanese: $languages['ja'] ?? null,
            url: is_string($page['fullurl'] ?? null) ? $page['fullurl'] : Site::pageUrl($title),
            profileImageUrl: $image ?? ($imageFile === null ? null : Site::specialFilePath($imageFile)),
            aliases: $aliases,
            tags: Photos::categories($page),
            rawProfile: self::rawProfile($wikidata, $sortkey, $info),
            metadata: array_filter([
                'wikidata_id' => $wikidata,
                'wikidata_url' => $wikidata === null ? null : 'https://www.wikidata.org/wiki/' . $wikidata,
                'wikipedia_links' => self::wikipediaLinks($wikitext),
                'descriptions' => self::descriptions($wikitext),
                'language_titles' => $languages,
                'sortkey' => $sortkey,
                'category_info' => $info,
                'profile_image_file' => $imageFile,
                'profile_image_width' => $original['width'] ?? null,
                'profile_image_height' => $original['height'] ?? null,
                'profile_thumbnail_url' => is_string($thumbnail['source'] ?? null) ? Site::cleanMediaUrl($thumbnail['source']) : null,
                'profile_thumbnail_width' => $thumbnail['width'] ?? null,
                'profile_thumbnail_height' => $thumbnail['height'] ?? null,
                'external_links' => self::externalLinks($page),
                'touched' => $page['touched'] ?? null,
                'last_revision_id' => $page['lastrevid'] ?? null,
                'last_edited_at' => self::revision($page)['timestamp'] ?? null,
                'member_added_at' => Markup::isoTimestamp($member['timestamp'] ?? null),
            ], static fn(mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
        );
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, string>
     */
    private static function rawProfile(?string $wikidata, mixed $sortkey, array $info): array
    {
        $rows = [];
        if ($wikidata !== null) {
            $rows['Wikidata'] = $wikidata;
        }
        if (is_string($sortkey) && $sortkey !== '') {
            $rows['Default sort'] = $sortkey;
        }
        foreach (['files' => 'Files', 'subcats' => 'Subcategories', 'pages' => 'Pages'] as $key => $label) {
            if (is_int($info[$key] ?? null)) {
                $rows[$label] = (string) $info[$key];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private static function revision(array $page): array
    {
        $revisions = is_array($page['revisions'] ?? null) ? $page['revisions'] : [];

        return CommonsApi::assoc($revisions[0] ?? []);
    }

    /** @param array<string, mixed> $page */
    private static function wikitext(array $page): string
    {
        $slots = CommonsApi::assoc(self::revision($page)['slots'] ?? []);
        $content = CommonsApi::assoc($slots['main'] ?? [])['content'] ?? null;

        return is_string($content) ? $content : '';
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, string>
     */
    private static function languageTitles(array $page): array
    {
        $titles = [];
        foreach (is_array($page['langlinks'] ?? null) ? $page['langlinks'] : [] as $link) {
            $link = CommonsApi::assoc($link);
            if (is_string($link['lang'] ?? null) && is_string($link['title'] ?? null) && $link['title'] !== '') {
                $titles[$link['lang']] = $link['title'];
            }
        }

        return $titles;
    }

    /** @return array<string, string> */
    private static function descriptions(string $wikitext): array
    {
        $descriptions = [];
        if (preg_match_all('/\{\{\s*([a-z]{2,3}(?:-[a-z]+)?)\s*\|(.+?)\}\}(?=\s*(?:\n|\{\{|$))/su', $wikitext, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        foreach ($matches as $match) {
            $text = preg_replace('/\[\[:?(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $match[2]);
            $text = Markup::text(is_string($text) ? preg_replace("/'{2,}/", '', $text) : null);
            if ($text !== null) {
                $descriptions[$match[1]] = $text;
            }
        }

        return $descriptions;
    }

    /** @return array<string, string> */
    private static function wikipediaLinks(string $wikitext): array
    {
        $links = [];
        if (preg_match_all('/\[\[:([a-z]{2,3}):([^\]|]+)/u', $wikitext, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        foreach ($matches as $match) {
            $links[$match[1]] = 'https://' . $match[1] . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', trim($match[2])));
        }

        return $links;
    }

    /**
     * Outside identifiers and websites linked from the category (tooling links dropped; Wikidata external-id
     * redirects resolved to the target site).
     *
     * @param  array<string, mixed>  $page
     * @return list<string>
     */
    private static function externalLinks(array $page): array
    {
        $links = [];
        foreach (is_array($page['extlinks'] ?? null) ? $page['extlinks'] : [] as $link) {
            $url = CommonsApi::assoc($link)['url'] ?? null;
            if (! is_string($url)) {
                continue;
            }

            $resolved = self::resolveIdentifierLink($url) ?? $url;
            $host = strtolower((string) parse_url($resolved, PHP_URL_HOST));
            if ($host !== '' && ! self::isToolHost($host)) {
                $links[$resolved] = $resolved;
            }
        }

        return array_values($links);
    }

    private static function resolveIdentifierLink(string $url): ?string
    {
        if (! str_contains($url, 'wikidata-externalid-url.toolforge.org')) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $prefix = $query['url_prefix'] ?? null;
        $id = $query['id'] ?? null;

        return is_string($prefix) && is_string($id) ? $prefix . $id : null;
    }

    private static function isToolHost(string $host): bool
    {
        foreach (self::TOOL_HOSTS as $tool) {
            if ($host === $tool || str_ends_with($host, '.' . $tool)) {
                return true;
            }
        }

        return false;
    }
}
