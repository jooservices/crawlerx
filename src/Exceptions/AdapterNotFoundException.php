<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Exceptions;

use JOOservices\CrawlerX\Registry\FileAdapterManifestRegistry;

final class AdapterNotFoundException extends AbstractCrawlerException
{
    public function __construct(string $name)
    {
        parent::__construct(
            in_array(strtolower($name), FileAdapterManifestRegistry::disabledSlugs(), true)
                ? "Adapter [{$name}] is disabled by CRAWLERX_DISABLED_SITES."
                : "Adapter [{$name}] is not registered.",
        );
    }
}
