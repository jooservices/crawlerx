<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

use JOOservices\CrawlerX\Dto\UrlDetectionResult;

interface UrlDetectCapable
{
    public function detectUrl(string $url): UrlDetectionResult;
}
