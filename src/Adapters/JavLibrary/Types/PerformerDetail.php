<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters\JavLibrary\Types;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\AbstractType;
use JOOservices\CrawlerX\Adapters\Concerns\ParsesHtml;
use JOOservices\CrawlerX\Adapters\JavLibrary\Selectors;
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
    ) {
    }

    public function execute(CrawlRequestDto $request): CrawlItemResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'JavLibrary', 'performer_detail');
        $crawler = new Crawler($html, $request->url);

        $nameNode = $crawler->filter(Selectors::PERFORMER_NAME);
        if ($nameNode->count() === 0) {
            throw new \RuntimeException('Performer name element not found.');
        }

        $name = trim(preg_replace([
            '/\s*-\s*JAVLibrary/i',
            '/^Videos\s+starring\s+/i',
        ], '', $nameNode->first()->text('')) ?? '');
        $externalId = $this->externalId($request->url);
        $rawProfile = $this->rawProfile($crawler);

        $data = array_filter([
            'external_id' => $externalId,
            'external_url' => $request->url,
            'name' => $name !== '' ? $name : null,
            'raw_profile' => $rawProfile !== '' ? $rawProfile : null,
        ], fn(mixed $value): bool => $value !== null && $value !== '');

        $item = PerformerDto::item(
            url: $request->url,
            externalId: $externalId,
            title: $name,
            data: $data,
        );

        $this->assertUsablePerformerDetail($item, 'javlibrary');

        return $item;
    }

    private function rawProfile(Crawler $crawler): string
    {
        $profile = $crawler->filter('#starprofile');
        if ($profile->count() === 0) {
            return $this->firstText($crawler, '.boxtitle') ?? '';
        }

        return $this->normalizeText($profile->first()->text('')) ?? '';
    }

    private function externalId(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $id = $params['st'] ?? $params['s'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }
}
