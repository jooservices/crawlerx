<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavBus\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavBus\Selectors;
use JOOservices\CrawlerX\Adapters\JavBus\UrlNormalizer;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavBus', 'performer_detail');
        $crawler = new Crawler($html, $request->url);
        $externalId = $this->performerExternalId($request->url);
        $fields = $this->profileFields($crawler);
        $name = $this->firstText($crawler, Selectors::PERFORMER_NAME) ?? $fields['name'] ?? null;
        $aliases = $this->aliases($crawler, $fields);
        $tags = $this->texts($crawler, Selectors::PERFORMER_TAG_LINKS);
        $sizeRaw = $this->sizeRaw($fields);
        $imageUrl = $this->profileImageUrl($crawler, $request->url);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $this->normalizer->canonical($request->url),
            'name' => $name,
            'name_japanese' => $fields['name_japanese'] ?? null,
            'profile_image_url' => $imageUrl,
            'birth_date_raw' => $fields['birth_date_raw'] ?? null,
            'height_raw' => $fields['height_raw'] ?? null,
            'size_raw' => $sizeRaw,
            'aliases' => $aliases !== [] ? $aliases : null,
            'tags' => $tags !== [] ? $tags : null,
            'raw_profile' => $this->rawProfile($crawler),
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $this->normalizer->canonical($request->url),
            externalId: $externalId,
            title: is_string($name) ? $name : '',
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'JavBus');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function profileFields(Crawler $crawler): array
    {
        $fields = [];

        $crawler->filter(Selectors::PERFORMER_INFO)->each(function (Crawler $node) use (&$fields): void {
            $text = $this->normalizeText($node->text('')) ?? '';
            if ($text === '') {
                return;
            }

            if ($node->filter('label')->count() > 0) {
                $label = $this->normalizeText($node->filter('label')->first()->text('')) ?? '';
                $value = trim(str_replace($node->filter('label')->first()->text(''), '', $node->text('')), " \t\n\r\0\x0B:：");
                $key = $this->fieldKey($label);
                if ($key !== null && $value !== '') {
                    $fields[$key] = $value;
                }

                return;
            }

            if (! isset($fields['name']) && $node->ancestors()->filter('h3')->count() === 0) {
                $fields['name'] = $text;
            }
        });

        $alias = $this->firstText($crawler, Selectors::PERFORMER_ALIAS);
        if ($alias !== null) {
            $fields['name_japanese'] = $alias;
        }

        return $fields;
    }

    private function fieldKey(string $label): ?string
    {
        return match (strtolower(rtrim($label, ':：'))) {
            'birthday' => 'birth_date_raw',
            'height' => 'height_raw',
            'measurements' => 'measurements_raw',
            'cup' => 'cup_raw',
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $fields
     * @return list<string>
     */
    private function aliases(Crawler $crawler, array $fields): array
    {
        $aliases = [];
        $alias = $this->firstText($crawler, Selectors::PERFORMER_ALIAS);
        if ($alias !== null) {
            $aliases[] = $alias;
        }

        if (isset($fields['name_japanese']) && ($fields['name'] ?? null) !== $fields['name_japanese']) {
            $aliases[] = $fields['name_japanese'];
        }

        return array_values(array_unique(array_filter($aliases, fn(string $alias): bool => $alias !== '')));
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function sizeRaw(array $fields): ?string
    {
        $measurements = $fields['measurements_raw'] ?? null;
        $cup = $fields['cup_raw'] ?? null;

        if ($measurements === null) {
            return null;
        }

        if ($cup !== null && preg_match('/(\d{2,3})\s*[-\/]\s*(\d{2,3})\s*[-\/]\s*(\d{2,3})/', $measurements, $match) === 1) {
            return "B{$match[1]}({$cup}) W{$match[2]} H{$match[3]}";
        }

        return $measurements;
    }

    private function profileImageUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $src = $this->firstAttribute($crawler, Selectors::PERFORMER_IMAGE, 'src');

        return $src === null ? null : $this->normalizer->absoluteAndCanonical($baseUrl, $src);
    }

    private function rawProfile(Crawler $crawler): ?string
    {
        $node = $crawler->filter('.photo-info');
        if ($node->count() === 0) {
            return null;
        }

        return $this->normalizeText($node->first()->text(''));
    }

    private function performerExternalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/(?:en/)?star/([^/]+)/?$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
