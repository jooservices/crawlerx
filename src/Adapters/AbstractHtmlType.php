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

        return new Crawler($html, $request->url);
    }
}
