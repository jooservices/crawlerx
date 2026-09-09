<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Xcity\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'xcity', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $profile = $crawler->filter('dl.profile')->count() > 0 ? $crawler->filter('dl.profile')->first() : null;
        $text = $this->cleanText(($profile ?? $crawler)->text(''));
        $fields = $profile instanceof Crawler ? $this->profileFieldsFromDom($profile) : [];
        $name = $this->firstHeading($crawler) ?? $fields['name'] ?? null;
        $imageUrl = $this->firstAttribute($crawler, '.photo img.actressThumb, p.tn img.actressThumb, img.actressThumb', 'src');

        $data = array_filter([
            'external_id' => $this->externalId($request->url),
            'external_url' => $request->url,
            'name' => is_string($name) ? $this->cleanName($name) : null,
            'profile_image_url' => is_string($imageUrl) ? UriResolver::resolve($imageUrl, $request->url) : null,
            'birth_date_raw' => $fields['date_of_birth'] ?? null,
            'blood_type' => $fields['blood_type'] ?? null,
            'birthplace' => $fields['city_of_born'] ?? null,
            'height_raw' => $fields['height'] ?? null,
            'size_raw' => $fields['size'] ?? null,
            'hobby' => $fields['hobby'] ?? null,
            'special_skill' => $fields['special_skill'] ?? null,
            'other' => $fields['other'] ?? null,
            'favorite_count_raw' => $fields['favorite_count'] ?? null,
            'raw_profile' => $text,
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $data['external_id'] ?? sha1(trim($request->url)),
            title: $data['name'] ?? '',
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'xcity');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function profileFieldsFromDom(Crawler $profile): array
    {
        $fields = [];

        $profile->filter('dd')->each(function (Crawler $node) use (&$fields): void {
            $labelNode = $node->filter('.koumoku');
            if ($labelNode->count() === 0) {
                return;
            }

            $label = $this->cleanText($labelNode->first()->text(''));
            $key = $this->profileKey($label);
            if ($key === null) {
                return;
            }

            $value = $this->cleanText(str_replace($label, '', $node->text('')));
            if ($key === 'blood_type') {
                $value = preg_match('/\b(AB|A|B|O)\b/iu', $value, $bloodMatch) === 1 ? strtoupper($bloodMatch[1]) : $value;
            }

            if ($value !== '') {
                $fields[$key] = $value;
            }
        });

        return $fields;
    }

    private function firstHeading(Crawler $crawler): ?string
    {
        foreach (['h1', 'h2', '.idol-name', '.ttl'] as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() === 0) {
                continue;
            }

            $text = $this->cleanName($nodes->first()->text(''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function firstAttribute(Crawler $crawler, string $selector, string $attribute): ?string
    {
        $nodes = $crawler->filter($selector);
        if ($nodes->count() === 0) {
            return null;
        }

        $value = $nodes->first()->attr($attribute);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function profileKey(string $label): ?string
    {
        return match ($this->cleanText($label)) {
            '★Favorite', 'Favorite' => 'favorite_count',
            'Date of birth' => 'date_of_birth',
            'Blood Type' => 'blood_type',
            'City of Born' => 'city_of_born',
            'Height' => 'height',
            'Size' => 'size',
            'Hobby' => 'hobby',
            'Special Skill' => 'special_skill',
            'Other' => 'other',
            default => null,
        };
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/idol/detail/([^/]+)/?#i', $path, $match) === 1) {
            return $match[1];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            $id = $params['id'] ?? null;

            return is_string($id) && trim($id) !== '' ? trim($id) : null;
        }

        return null;
    }

    private function cleanName(string $value): string
    {
        $value = preg_replace('/\s*All Titles Information\s*$/iu', '', $value) ?? $value;

        return $this->cleanText($value);
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));
    }
}
