<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\MinnanoAv\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\MinnanoAv\Selectors;
use JOOservices\CrawlerX\Adapters\MinnanoAv\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    protected function htmlFromResponse(ResponseInterface $response, string $site, string $context): string
    {
        $html = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        $isChallenge = str_contains($html, 'cf-browser-verification')
            || str_contains($html, 'cf-mitigated')
            || str_contains($html, 'Just a moment...')
            || str_contains($html, 'Attention Required!');

        if ($isChallenge) {
            throw new \RuntimeException("{$site} {$context} page is blocked or unavailable.");
        }

        if (trim($html) === '') {
            throw new \RuntimeException("{$site} {$context} page is empty.");
        }

        return $html;
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'minnanoav', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $heading = $this->heading($crawler);
        $fields = $this->profileFields($crawler);
        $externalId = $this->externalId($request->url);
        $name = $heading['name'] ?? $fields['name'] ?? null;
        $imageUrl = $this->profileImageUrl($crawler, $request->url);
        $sizeRaw = $fields['size_raw'] ?? null;
        $heightRaw = $this->heightRaw($sizeRaw, $fields['height_raw'] ?? null);
        $aliases = $this->aliases($heading, $fields);
        $tags = $this->tags($crawler);
        $rawProfile = $this->rawProfile($crawler);
        $metadata = $this->metadata($crawler, $fields, $heading);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $this->normalizer->canonicalActressUrl($request->url),
            'filmography_url' => $this->filmographyUrl($externalId),
            'name' => $name,
            'name_japanese' => $heading['name_japanese'] ?? null,
            'name_romaji' => $heading['name_romaji'] ?? null,
            'profile_image_url' => $imageUrl,
            'birth_date_raw' => $this->normalizeJapaneseDate($fields['birth_date_raw'] ?? null),
            'height_raw' => $heightRaw,
            'size_raw' => $sizeRaw,
            'birthplace' => $fields['birthplace'] ?? null,
            'hobby' => $fields['hobby'] ?? null,
            'special_skill' => $fields['special_skill'] ?? null,
            'other' => $fields['nickname'] ?? null,
            'zodiac_sign' => $fields['zodiac_sign'] ?? null,
            'aliases' => $aliases !== [] ? $aliases : null,
            'tags' => $tags !== [] ? $tags : null,
            'raw_profile' => $rawProfile !== '' ? $rawProfile : null,
            'metadata' => $metadata !== [] ? $metadata : null,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $this->normalizer->canonicalActressUrl($request->url),
            externalId: $externalId,
            title: is_string($name) ? $name : '',
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'minnanoav');

        return $item;
    }

    /**
     * @return array{name?: string, name_japanese?: string, name_romaji?: string}
     */
    private function heading(Crawler $crawler): array
    {
        $node = $crawler->filter(Selectors::PERFORMER_HEADING);
        if ($node->count() === 0) {
            return [];
        }

        $name = $this->normalizeText($node->text('')) ?? '';
        $sub = $node->first()->filter('span');
        $reading = $sub->count() > 0 ? ($this->normalizeText($sub->first()->text('')) ?? '') : '';

        if ($sub->count() > 0) {
            $name = $this->normalizeText(str_replace($sub->first()->text(''), '', $node->first()->text(''))) ?? $name;
        }

        $result = [];
        if ($name !== '') {
            $result['name'] = $name;
        }

        if ($reading !== '') {
            $parts = array_values(array_filter(array_map('trim', explode('/', $reading)), static fn(string $part): bool => $part !== ''));
            if (($parts[0] ?? '') !== '') {
                $result['name_japanese'] = $parts[0];
            }
            if (($parts[1] ?? '') !== '') {
                $result['name_romaji'] = $parts[1];
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function profileFields(Crawler $crawler): array
    {
        $fields = [];

        $crawler->filter(Selectors::PROFILE_ROW)->each(function (Crawler $row) use (&$fields): void {
            $labelNode = $row->filter(Selectors::PROFILE_LABEL);
            if ($labelNode->count() === 0) {
                return;
            }

            $label = $this->normalizeText($labelNode->first()->text('')) ?? '';
            $key = $this->fieldKey($label);
            if ($key === null) {
                return;
            }

            $valueNode = $row->filter('p, td')->last();
            $value = $this->normalizeText(str_replace($label, '', $valueNode->text(''))) ?? '';
            $value = trim($value, " \t\n\r\0\x0B:：");

            if ($key === 'birth_date_raw') {
                $value = $this->extractBirthDateLine($value);
            }

            if ($value !== '') {
                $fields[$key] = $value;
            }
        });

        if (isset($fields['name']) && str_contains($fields['name'], '/')) {
            unset($fields['name']);
        }

        return $fields;
    }

    private function fieldKey(string $label): ?string
    {
        return match ($label) {
            '愛称' => 'nickname',
            '別名' => 'alias_raw',
            '生年月日' => 'birth_date_raw',
            'サイズ' => 'size_raw',
            '出身地' => 'birthplace',
            '所属事務所' => 'agency',
            '趣味・特技' => 'hobby',
            'AV出演期間' => 'career_period',
            'デビュー作品' => 'debut_work',
            'ブログ' => 'blog_url',
            '公式サイト' => 'official_url',
            default => null,
        };
    }

    private function extractBirthDateLine(string $value): string
    {
        if (preg_match('/(\d{4}年\d{1,2}月\d{1,2}日)/u', $value, $match) === 1) {
            $date = $match[1];
            if (preg_match('/(おひつじ座|おとめ座|てんびん座|さそり座|いて座|やぎ座|みずがめ座|うお座|おうし座|ふたご座|かに座|しし座)/u', $value, $zodiacMatch) === 1) {
                return $date . ' ' . $zodiacMatch[1];
            }

            return $date;
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array{name?: string, name_japanese?: string, name_romaji?: string}  $heading
     * @return list<string>
     */
    private function aliases(array $heading, array $fields): array
    {
        $aliases = [];

        if (isset($heading['name_japanese']) && isset($heading['name']) && $heading['name_japanese'] !== $heading['name']) {
            $aliases[] = $heading['name_japanese'];
        }

        if (isset($fields['alias_raw'])) {
            $aliasLine = trim(preg_replace('/[（(].*[）)]/u', '', $fields['alias_raw']) ?? $fields['alias_raw']);
            if ($aliasLine !== '' && ($heading['name'] ?? null) !== $aliasLine) {
                $aliases[] = $aliasLine;
            }
        }

        return array_values(array_unique(array_filter($aliases, static fn(string $alias): bool => $alias !== '')));
    }

    /**
     * @return list<string>
     */
    private function tags(Crawler $crawler): array
    {
        $tags = [];
        $crawler->filter(Selectors::PROFILE_TAGS)->each(function (Crawler $node) use (&$tags): void {
            $text = $this->normalizeText($node->text('')) ?? '';
            if ($text !== '') {
                $tags[$text] = true;
            }
        });

        return array_keys($tags);
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array{name?: string, name_japanese?: string, name_romaji?: string}  $heading
     * @return array<string, mixed>
     */
    private function metadata(Crawler $crawler, array $fields, array $heading): array
    {
        $metadata = array_filter([
            'nickname' => $fields['nickname'] ?? null,
            'agency' => $fields['agency'] ?? null,
            'career_period' => $fields['career_period'] ?? null,
            'debut_work' => $fields['debut_work'] ?? null,
            'blog_url' => $fields['blog_url'] ?? null,
            'official_url' => $fields['official_url'] ?? null,
            'name_reading' => $heading['name_japanese'] ?? null,
            'name_romaji' => $heading['name_romaji'] ?? null,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');

        $crawler->filter(Selectors::RATING_TABLE . ' tr')->each(function (Crawler $row) use (&$metadata): void {
            $cells = $row->filter('td');
            if ($cells->count() < 3) {
                return;
            }

            $label = $this->normalizeText($cells->eq(0)->text('')) ?? '';
            $score = $this->normalizeText($cells->eq(2)->text('')) ?? '';
            if ($label !== '' && $score !== '' && is_numeric($score)) {
                $metadata['rating_' . mb_strtolower($label)] = (float) $score;
            }
        });

        if (isset($fields['birth_date_raw']) && preg_match('/(おひつじ座|おとめ座|てんびん座|さそり座|いて座|やぎ座|みずがめ座|うお座|おうし座|ふたご座|かに座|しし座)/u', $fields['birth_date_raw'], $match) === 1) {
            $metadata['zodiac_sign'] = $match[1];
        }

        return $metadata;
    }

    private function profileImageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PERFORMER_IMAGE);
        if ($node->count() === 0) {
            $meta = $crawler->filter('meta[property="og:image"]');
            if ($meta->count() === 0) {
                return null;
            }

            $content = $meta->first()->attr('content');

            return is_string($content) && trim($content) !== ''
                ? $this->normalizer->absolute($baseUrl, $content)
                : null;
        }

        $src = $node->first()->attr('src');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        return $this->normalizer->absolute($baseUrl, $src);
    }

    private function heightRaw(?string $sizeRaw, ?string $existing): ?string
    {
        if ($existing !== null && preg_match('/(\d{2,3})\s*cm/i', $existing, $match) === 1) {
            return $match[1] . ' cm';
        }

        if ($sizeRaw !== null && preg_match('/\bT\s*(\d{2,3})\b/u', $sizeRaw, $match) === 1) {
            return $match[1] . ' cm';
        }

        return null;
    }

    private function normalizeJapaneseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/u', $value, $match) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3]);
        }

        return trim(preg_replace('/(おひつじ座|おとめ座|てんびん座|さそり座|いて座|やぎ座|みずがめ座|うお座|おうし座|ふたご座|かに座|しし座)/u', '', $value) ?? $value);
    }

    private function rawProfile(Crawler $crawler): string
    {
        $table = $crawler->filter(Selectors::PROFILE_TABLE);
        if ($table->count() === 0) {
            return '';
        }

        return $this->normalizeText($table->first()->text('')) ?? '';
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/actress(\d+)\.html$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private function filmographyUrl(?string $externalId): ?string
    {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        return 'https://www.minnano-av.com/actress.php?actress_id=' . $externalId;
    }
}
