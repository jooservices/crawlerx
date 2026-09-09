<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Adapters;

use JOOservices\CrawlerX\Contracts\TypeInterface;
use JOOservices\CrawlerX\Dto\CrawlItemResultDto;
use JOOservices\CrawlerX\Dto\CrawlListResultDto;
use JOOservices\CrawlerX\Dto\CrawlRequestDto;

abstract class SimpleHtmlCrawler extends AbstractBaseCrawler
{
    protected string $listingType = '';

    protected string $detailType = '';

    /**
     * @return class-string<TypeInterface>
     */
    protected function defaultListingType(): string
    {
        if ($this->listingType === '' || ! is_a($this->listingType, TypeInterface::class, true)) {
            throw new \RuntimeException(static::class . ' must set $listingType to a TypeInterface class.');
        }

        /** @var class-string<TypeInterface> $type */
        $type = $this->listingType;

        return $type;
    }

    /**
     * @return class-string<TypeInterface>
     */
    protected function defaultDetailType(): string
    {
        if ($this->detailType === '' || ! is_a($this->detailType, TypeInterface::class, true)) {
            throw new \RuntimeException(static::class . ' must set $detailType to a TypeInterface class.');
        }

        /** @var class-string<TypeInterface> $type */
        $type = $this->detailType;

        return $type;
    }

    public function listing(CrawlRequestDto $request): CrawlListResultDto
    {
        $type = $this->listingRoutes() !== []
            ? $this->resolveListingType($request)
            : $this->defaultListingType();

        /** @var CrawlListResultDto $result */
        $result = $this->makeType($type)->execute($request);

        return $result;
    }

    public function detail(CrawlRequestDto $request): CrawlItemResultDto
    {
        $type = $this->detailRoutes() !== []
            ? $this->resolveDetailType($request)
            : $this->defaultDetailType();

        /** @var CrawlItemResultDto $result */
        $result = $this->makeType($type)->execute($request);

        return $result;
    }
}
