<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Wpb\Types;

use JOOservices\CrawlerX\Adapters\Wpb\Selectors;
use JOOservices\CrawlerX\Adapters\Wpb\Urls;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\GalleryDto;
use JOOservices\CrawlerX\Dto\Entity\PhotoDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use JOOservices\CrawlerX\Support\MovieMeta;
use Symfony\Component\DomCrawler\Crawler;

final class Gallery extends WpbHtmlType implements TypeInterface
{
    protected function contextLabel(): string
    {
        return 'gallery';
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $url = Urls::gallery($request->url);
        $crawler = $this->fetchCrawler(new CrawlRequestDto(url: $url, type: $request->type));
        $externalId = Urls::externalId($url);
        $title = $this->firstText($crawler, Selectors::DETAIL_TITLE)
            ?? $this->firstAttribute($crawler, Selectors::OG_TITLE, 'content');
        $cover = $this->cover($crawler, $url);
        if ($externalId === null || $title === null || $cover === null) {
            throw new CrawlParseException('WPB gallery page did not contain expected gallery fields.');
        }

        $published = $this->firstAttribute($crawler, Selectors::DETAIL_DATE, 'datetime')
            ?? $this->firstText($crawler, Selectors::DETAIL_DATE);
        $body = $this->bodyParts($crawler, $url);
        $profile = $body['profile'];
        $performers = array_values(array_filter(
            [
                is_string($profile['name'] ?? null) ? $profile['name'] : null,
                $this->performerFromTitle($title),
            ],
            static fn(?string $name): bool => $name !== null,
        ));
        $performers = array_values(array_unique($performers));
        $tags = $this->tagNames($crawler);
        $coverUrl = $this->stringField($cover, 'url');
        if ($coverUrl === null) {
            throw new CrawlParseException('WPB gallery page did not contain expected gallery fields.');
        }
        $photos = [$this->coverPhoto($cover, $coverUrl, $url)];
        $ogImage = $this->firstAttribute($crawler, Selectors::OG_IMAGE, 'content');
        if (is_string($ogImage) && $ogImage !== '' && $ogImage !== $coverUrl && ! $this->isChromeImage($ogImage)) {
            $absolute = $this->absolute($url, $ogImage);
            if ($absolute !== $coverUrl) {
                $photos[] = new PhotoDto(
                    url: $absolute,
                    imageUrl: $absolute,
                    position: 2,
                    metadata: MovieMeta::filter([
                        'mime_type' => Urls::mimeType($absolute),
                        'kind' => 'og_image',
                        'source_page_url' => $url,
                    ]),
                );
            }
        }

        $sourceFields = $this->sourceFields($published, $profile, $body['credits'], $tags, $body['extras']);

        return GalleryDto::item(
            url: $url,
            externalId: $externalId,
            title: $title,
            data: [
                'photo_count' => count($photos),
                'date' => MovieMeta::isoDate($published),
                'performers' => $performers,
                'categories' => $this->categories($crawler),
                'tags' => $tags,
                'photos' => $photos,
                'metadata' => MovieMeta::filter([
                    'published_at' => $published,
                    MovieMeta::MAKER => '週刊プレイボーイ',
                    'photographer' => $this->creditName($body['credits'], '撮影'),
                    'credits' => $body['credits'],
                    'profile' => MovieMeta::filter($profile),
                    'description' => $this->firstAttribute($crawler, Selectors::OG_DESCRIPTION, 'content')
                        ?? ($body['paragraphs'][0] ?? null),
                    'body' => $body['paragraphs'],
                    'cover' => MovieMeta::filter($cover + ['mime_type' => Urls::mimeType($coverUrl)]),
                    'tag_urls' => $this->tagUrls($crawler, $url),
                    'links' => $body['links'],
                    'attached_dvd_duration_minutes' => $body['dvd_minutes'],
                    'source_fields' => $sourceFields,
                ]),
            ],
        );
    }

    /** @return array<string, mixed>|null */
    private function cover(Crawler $crawler, string $baseUrl): ?array
    {
        $nodes = $crawler->filter(Selectors::DETAIL_COVER);
        if ($nodes->count() === 0) {
            $og = $this->firstAttribute($crawler, Selectors::OG_IMAGE, 'content');

            return $og === null || $this->isChromeImage($og) ? null : MovieMeta::filter([
                'url' => $this->absolute($baseUrl, $og),
                'mime_type' => Urls::mimeType($this->absolute($baseUrl, $og)),
            ]);
        }

        $picture = $this->picture($nodes->first(), $baseUrl);

        return $picture === null ? null : MovieMeta::filter($picture);
    }

    /** @param array<string, mixed> $cover */
    private function coverPhoto(array $cover, string $coverUrl, string $pageUrl): PhotoDto
    {
        return new PhotoDto(
            url: $coverUrl,
            imageUrl: $coverUrl,
            thumbnailUrl: $this->stringField($cover, 'thumbnail_url'),
            position: 1,
            metadata: MovieMeta::filter([
                'alt' => $cover['alt'] ?? null,
                'width' => $cover['width'] ?? null,
                'height' => $cover['height'] ?? null,
                'mime_type' => Urls::mimeType($coverUrl),
                'kind' => 'cover',
                'source_page_url' => $pageUrl,
            ]),
        );
    }

    /** @param array<string, mixed> $data */
    private function stringField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{
     *   paragraphs: list<string>,
     *   profile: array<string, mixed>,
     *   credits: list<array{role: string, name: string}>,
     *   links: list<array{name: string, url: string}>,
     *   extras: array<string, string>,
     *   dvd_minutes: ?int
     * }
     */
    private function bodyParts(Crawler $crawler, string $baseUrl): array
    {
        $parts = [
            'paragraphs' => [],
            'profile' => [],
            'credits' => [],
            'links' => [],
            'extras' => [],
            'dvd_minutes' => null,
        ];
        $body = $crawler->filter(Selectors::DETAIL_BODY);
        if ($body->count() === 0) {
            return $parts;
        }

        $body->first()->filter('a[href]')->each(function (Crawler $anchor) use (&$parts, $baseUrl): void {
            $href = $anchor->attr('href');
            $name = $this->normalizeText($anchor->text(''));
            if (! is_string($href) || $name === null || $this->isChromeLink($href)) {
                return;
            }
            $absolute = $this->absolute($baseUrl, $href);
            $parts['links'][$absolute] = ['name' => $name, 'url' => $absolute];
        });
        $parts['links'] = array_values($parts['links']);

        $body->first()->filter('p')->each(function (Crawler $paragraph) use (&$parts): void {
            $rawLines = preg_split('#<br\s*/?>#i', $paragraph->html(''));
            $lines = [];
            foreach ($rawLines === false ? [] : $rawLines as $line) {
                $clean = $this->clean(strip_tags($line));
                if ($clean !== null) {
                    $lines[] = $clean;
                }
            }
            if ($lines === []) {
                return;
            }

            foreach ($lines as $line) {
                if (preg_match('/^■(.+?)(?:（([^）]+)）)?$/u', $line, $match) === 1) {
                    $parts['profile']['name'] = $this->clean($match[1]);
                    $nameEn = $this->clean($match[2] ?? '');
                    if ($nameEn !== null) {
                        $parts['profile']['name_en'] = $nameEn;
                    }
                    $parts['extras']['profile_header'] = $line;
                    continue;
                }
                if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日生まれ(?:\s+(.+))?$/u', $line, $match) === 1) {
                    $parts['profile']['birthday'] = sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3]);
                    $parts['extras']['birthday_raw'] = $line;
                    $placeRaw = $this->clean($match[4] ?? '');
                    if ($placeRaw !== null) {
                        $birthplace = $this->clean((string) preg_replace('/出身$/u', '', $placeRaw));
                        if ($birthplace !== null) {
                            $parts['profile']['birthplace'] = $birthplace;
                        }
                    }
                    continue;
                }
                if (preg_match('/身長\s*(\d+)\s*[㎝cm]/iu', $line, $match) === 1) {
                    $parts['profile']['height_cm'] = (int) $match[1];
                    $parts['extras']['measurements_raw'] = $line;
                    if (preg_match('/B(\d+)\s*W(\d+)\s*H(\d+)/iu', $line, $sizes) === 1) {
                        $parts['profile']['bust'] = (int) $sizes[1];
                        $parts['profile']['waist'] = (int) $sizes[2];
                        $parts['profile']['hips'] = (int) $sizes[3];
                    }
                    continue;
                }
                if (preg_match('#^(撮影|スタイリング|ヘア＆メイク|ヘアメイク)\s*[/／]\s*(.+)$#u', $line, $match) === 1) {
                    $credit = ['role' => $match[1], 'name' => $this->clean($match[2]) ?? $match[2]];
                    $key = $credit['role'] . "\0" . $credit['name'];
                    $parts['credits'][$key] = $credit;
                    continue;
                }
                if (preg_match('/特別付録DVD（(\d+)\s*分）/u', $line, $match) === 1) {
                    $parts['dvd_minutes'] = (int) $match[1];
                    $parts['extras']['dvd'] = $line;
                }
            }

            if ($this->isCreditOnly($lines) || $this->isProfileHeaderOnly($lines)) {
                return;
            }

            $parts['paragraphs'][] = implode("\n", $lines);
        });

        $parts['credits'] = array_values($parts['credits']);

        return $parts;
    }

    /** @param list<string> $lines */
    private function isCreditOnly(array $lines): bool
    {
        foreach ($lines as $line) {
            if (preg_match('#^(撮影|スタイリング|ヘア＆メイク|ヘアメイク)\s*[/／]#u', $line) !== 1) {
                return false;
            }
        }

        return $lines !== [];
    }

    /** @param list<string> $lines */
    private function isProfileHeaderOnly(array $lines): bool
    {
        return count($lines) === 1 && str_starts_with($lines[0], '■');
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<array{role: string, name: string}> $credits
     * @param list<string> $tags
     * @param array<string, string> $extras
     * @return array<string, string>
     */
    private function sourceFields(?string $published, array $profile, array $credits, array $tags, array $extras): array
    {
        $fields = [];
        if ($published !== null) {
            $fields['公開日時'] = $published;
        }
        $profileLabels = [
            'name' => '名前',
            'name_en' => '名前（英字）',
            'birthday' => '生年月日',
            'birthplace' => '出身地',
            'height_cm' => '身長',
            'bust' => 'B',
            'waist' => 'W',
            'hips' => 'H',
        ];
        foreach ($profileLabels as $key => $label) {
            if (! array_key_exists($key, $profile) || ! is_scalar($profile[$key]) || $profile[$key] === '') {
                continue;
            }
            $fields[$label] = (string) $profile[$key];
        }
        foreach ($credits as $credit) {
            $fields[$credit['role']] = $credit['name'];
        }
        if ($tags !== []) {
            $fields['キーワードタグ'] = implode(', ', $tags);
        }
        foreach ($extras as $key => $value) {
            $fields[$key] = $value;
        }

        return $fields;
    }

    /** @return list<string> */
    private function categories(Crawler $crawler): array
    {
        $names = $this->texts($crawler, Selectors::DETAIL_BREADCRUMB);
        $categories = [];
        foreach ($names as $name) {
            if (in_array($name, ['週プレNEWS TOP', 'グラビア一覧'], true) || str_contains($name, '『')) {
                continue;
            }
            $categories[] = $name;
        }

        return $categories === [] ? ['グラビア'] : array_values(array_unique($categories));
    }

    /** @return list<string> */
    private function tagNames(Crawler $crawler): array
    {
        $keywords = $crawler->filter('ul.navi-button-a--keywords')->first();
        if ($keywords->count() === 0) {
            return [];
        }

        return $this->texts($keywords, 'a.navi-button-a__button .navi-button-a__button__label, a.navi-button-a__button');
    }

    /** @return list<string> */
    private function tagUrls(Crawler $crawler, string $baseUrl): array
    {
        $keywords = $crawler->filter('ul.navi-button-a--keywords')->first();
        if ($keywords->count() === 0) {
            return [];
        }

        return array_values(array_filter($keywords->filter('a.navi-button-a__button')->each(
            fn(Crawler $node): string => $this->absolute($baseUrl, (string) $node->attr('href')),
        ), static fn(string $href): bool => $href !== ''));
    }

    /** @param list<array{role: string, name: string}> $credits */
    private function creditName(array $credits, string $role): ?string
    {
        foreach ($credits as $credit) {
            if ($credit['role'] === $role) {
                return $credit['name'];
            }
        }

        return null;
    }

    private function performerFromTitle(string $title): ?string
    {
        return preg_match('/^(.+?)[『「]/u', $title, $match) === 1
            ? $this->clean($match[1])
            : null;
    }

    private function isChromeImage(string $url): bool
    {
        return str_contains($url, '/images/common/')
            || str_contains($url, '/images/bnr/')
            || str_contains($url, '/data/banner/')
            || str_contains($url, '4375d21660a17e6b0e50d5402d16cb885ab42bd1');
    }

    private function isChromeLink(string $href): bool
    {
        return str_starts_with($href, '#')
            || str_contains($href, 'twitter.com/intent')
            || str_contains($href, 'facebook.com/sharer')
            || str_contains($href, 'line.me/');
    }

    private function clean(string $text): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', html_entity_decode($text))));

        return $normalized === '' ? null : $normalized;
    }
}
