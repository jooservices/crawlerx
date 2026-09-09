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
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlPaginationDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

final class PerformerListing extends AbstractType implements TypeInterface
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

    public function execute(CrawlRequestDto $request): CrawlListResultDto
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse($response->toPsrResponse(), 'minnanoav', 'performer_listing');
        $crawler = new Crawler($html, $request->url);
        $items = $this->itemsFromJsonLd($html);

        if ($items === []) {
            $items = $this->itemsFromLinks($crawler, $request->url);
        }

        $nextUrl = $this->nextPageUrl($crawler, $html, $request->url);
        $lastPage = $this->lastPageNumber($crawler, $html);

        return new CrawlListResultDto(
            url: $request->url,
            page: $request->page,
            entityType: 'performer',
            items: array_values($items),
            pagination: new CrawlPaginationDto(
                currentPage: $request->page,
                lastPage: $lastPage,
                nextPage: $nextUrl !== null ? $this->pageNumber($nextUrl) : null,
                nextUrl: $nextUrl,
                hasNextPage: $nextUrl !== null,
            ),
        );
    }

    /**
     * @return array<string, CrawlItemResultDto>
     */
    private function itemsFromJsonLd(string $html): array
    {
        if (
            preg_match_all(
                '#"@type"\s*:\s*"Person"[^}]*"name"\s*:\s*"([^"]+)"[^}]*"url"\s*:\s*"(https://www\.minnano-av\.com/actress\d+\.html)"#s',
                $html,
                $matches,
                PREG_SET_ORDER,
            ) < 1
        ) {
            if (preg_match_all('#https://www\.minnano-av\.com/actress(\d+)\.html#', $html, $fallback) < 1) {
                return [];
            }

            $items = [];
            foreach ($fallback[1] as $id) {
                $this->pushItem($items, 'https://www.minnano-av.com/actress' . $id . '.html', $id);
            }

            return $items;
        }

        $items = [];

        foreach ($matches as $match) {
            $name = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
            $this->pushItem($items, $match[2], $name);
        }

        return $items;
    }

    /**
     * @return array<string, CrawlItemResultDto>
     */
    private function itemsFromLinks(Crawler $crawler, string $baseUrl): array
    {
        $items = [];

        $crawler->filter(Selectors::PERFORMER_LINKS)->each(function (Crawler $node) use (&$items, $baseUrl): void {
            $href = $node->attr('href');
            if (! is_string($href) || trim($href) === '') {
                return;
            }

            $absolute = $this->normalizer->absolute($baseUrl, $href);
            $canonical = $this->normalizer->canonicalActressUrl($absolute);
            $name = $this->normalizeText($node->text('')) ?? '';

            $this->pushItem($items, $canonical, $name !== '' ? $name : null);
        });

        return $items;
    }

    /**
     * @param  array<string, CrawlItemResultDto>  $items
     */
    private function pushItem(array &$items, string $url, ?string $name): void
    {
        $externalId = $this->externalId($url);
        if ($externalId === null) {
            return;
        }

        $existing = $items[$url] ?? null;
        $existingPerformer = $existing instanceof CrawlItemResultDto
            ? ($existing->meta['performer'] ?? null)
            : null;
        $existingName = is_array($existingPerformer) ? ($existingPerformer['name'] ?? null) : null;
        if (is_string($existingName) && trim($existingName) !== '') {
            return;
        }

        $items[$url] = PerformerDto::item(
            url: $url,
            externalId: $externalId,
            title: (is_string($name) && trim($name) !== '')
                ? $name
                : (is_string($existingName) ? $existingName : null),
        );
    }

    private function nextPageUrl(Crawler $crawler, string $html, string $baseUrl): ?string
    {
        $node = $crawler->filter('link[rel="next"]');
        if ($node->count() > 0) {
            $href = $node->first()->attr('href');
            if (is_string($href) && trim($href) !== '') {
                return $this->normalizer->absolute($baseUrl, $href);
            }
        }

        $next = $crawler->filter('.pagination a.next, .pagination a[class*="next"]');
        if ($next->count() > 0) {
            $href = $next->first()->attr('href');
            if (is_string($href) && trim($href) !== '') {
                return $this->normalizer->absolute($baseUrl, $href);
            }
        }

        if (preg_match('/<link rel="next" href="([^"]+)"/', $html, $match) === 1) {
            return $this->normalizer->absolute($baseUrl, $match[1]);
        }

        return null;
    }

    private function lastPageNumber(Crawler $crawler, string $html): ?int
    {
        $pageInfo = $crawler->filter('.pagination .page_info');
        if ($pageInfo->count() > 0) {
            $text = $pageInfo->first()->text('');
            if (preg_match('/\/\s*(\d+)\s*ページ/u', $text, $match) === 1) {
                return (int) $match[1];
            }
        }

        if (preg_match('/page_jump_input[^>]+max=[\'"](\d+)[\'"]/', $html, $match) === 1) {
            return (int) $match[1];
        }

        if (preg_match('/\/\s*(\d+)\s*ページ/u', $html, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function pageNumber(string $url): ?int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $page = $params['page'] ?? null;

        return is_numeric($page) ? (int) $page : null;
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/actress(\d+)\.html$#i', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
