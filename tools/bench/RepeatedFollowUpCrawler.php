<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Bench;

use JOOservices\CrawlerX\Adapters\AbstractBaseCrawler;
use JOOservices\CrawlerX\Contracts\GalleryCapable;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Services\ClientFactory;
use RuntimeException;

final class RepeatedFollowUpCrawler extends AbstractBaseCrawler implements GalleryCapable
{
    public static string $followUpUrl = '';

    public static bool $memoEnabled = true;

    public function name(): string
    {
        return 'repeated-follow-up-benchmark';
    }

    public function gallery(CrawlRequestDto $request): CrawlItemResultDto
    {
        if (self::$followUpUrl === '') {
            throw new RuntimeException('A follow-up URL is required for the repeated-follow-up benchmark.');
        }

        $client = self::$memoEnabled ? $this->client : (new ClientFactory())->factory($this->options);
        $bodies = [];
        for ($read = 0; $read < 2; $read++) {
            $bodies[] = (string) $client->get(self::$followUpUrl)->toPsrResponse()->getBody();
        }

        return new CrawlItemResultDto($request->url, 'gallery', ['follow_up_bodies' => $bodies]);
    }
}
