<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tests\Support;

use JOOservices\CrawlerX\Contracts\DetailCapable;
use JOOservices\CrawlerX\Contracts\SiteAdapter;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\Entity\MovieDto;

final class FakeServiceAdapter implements DetailCapable, SiteAdapter
{
    public function name(): string
    {
        return 'fake';
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        return MovieDto::item(
            url: $request->url,
            externalId: 'fake',
            title: 'Fake detail',
            data: ['source' => 'feature-test'],
        );
    }
}
