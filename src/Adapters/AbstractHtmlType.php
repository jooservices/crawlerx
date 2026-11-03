<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters;

use JOOservices\CrawlerX\Contracts\CrawlHttpClient;
use JOOservices\CrawlerX\Adapters\Concerns\NormalizesUrls;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractHtmlType extends AbstractType
{
    use NormalizesUrls;

    public function __construct(
        protected readonly CrawlHttpClient $client,
    ) {
    }

    abstract protected function siteLabel(): string;

    abstract protected function contextLabel(): string;

    protected function fetchCrawler(CrawlRequestDto $request): Crawler
    {
        $response = $this->client->get($request->url);
        $html = $this->htmlFromResponse(
            $response->toPsrResponse(),
            $this->siteLabel(),
            $this->contextLabel(),
        );

        return new Crawler($this->declaredCharsetFixed($html), $request->url);
    }

    /**
     * A page already decoded to UTF-8 (browser or proxy fetch) can still declare a legacy charset such as EUC-JP in
     * its <meta>, which makes DomCrawler decode it a second time. Raw legacy bytes are not valid UTF-8 and are left
     * for DomCrawler to convert.
     */
    private function declaredCharsetFixed(string $html): string
    {
        $pattern = '/(<meta[^>]+charset\s*=\s*["\']?)(?!utf-?8)[a-z0-9_:.-]+/i';

        return preg_match($pattern, $html) === 1 && mb_check_encoding($html, 'UTF-8')
            ? (string) preg_replace($pattern, '${1}utf-8', $html, 1)
            : $html;
    }
}
