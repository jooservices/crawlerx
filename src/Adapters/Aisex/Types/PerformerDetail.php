<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\Aisex\Types;

use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\Aisex\Selectors;
use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\Entity\PerformerDto;
use JOOservices\CrawlerX\Exceptions\CrawlParseException;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerDetail extends AbstractType implements TypeInterface
{
    use NormalizesUrls;
    use ParsesHtml;

    public function __construct(
        private readonly CrawlHttpClient $client,
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'aisex', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PROFILE_NAME);
        if ($nameNode->count() === 0) {
            throw new CrawlParseException('Aisex performer detail is missing a valid name.');
        }

        $name = $this->normalizeText($nameNode->first()->text('')) ?? '';
        $externalId = $this->externalId($request->url);
        if ($name === '') {
            throw new CrawlParseException('Aisex performer detail is missing a valid name.');
        }

        $spec = $this->specs($crawler);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name,
            'name_japanese' => $name,
            'name_kana' => $this->firstText($crawler, Selectors::PROFILE_RUBY),
            'birth_date_raw' => $spec['生年月日'] ?? null,
            'zodiac_sign' => $spec['星座'] ?? null,
            'blood_type' => $spec['血液型'] ?? null,
            'height_raw' => $spec['身長'] ?? null,
            'bust_raw' => $spec['バスト'] ?? null,
            'cup_size' => $spec['カップ'] ?? null,
            'waist_raw' => $spec['ウエスト'] ?? null,
            'hip_raw' => $spec['ヒップ'] ?? null,
            'profile_image_url' => $this->profileImage($crawler, $request->url),
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'aisex');

        return $item;
    }

    /**
     * @return array<string, string>
     */
    private function specs(Crawler $crawler): array
    {
        $spec = [];
        $crawler->filter(Selectors::SPEC_ITEMS)->each(function (Crawler $row) use (&$spec): void {
            $label = $this->firstText($row, Selectors::SPEC_LABEL);
            $value = $this->firstText($row, Selectors::SPEC_VALUE);
            if ($label !== null && $value !== null) {
                $spec[$label] = $value;
            }
        });

        return $spec;
    }

    private function profileImage(Crawler $crawler, string $baseUrl): ?string
    {
        $node = $crawler->filter(Selectors::PROFILE_IMAGE);
        if ($node->count() === 0) {
            return null;
        }

        $src = $node->first()->attr('src');
        if (! is_string($src) || trim($src) === '') {
            return null;
        }

        return $this->absolute($baseUrl, $src);
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#^/actress/(\d+)/?$#', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
