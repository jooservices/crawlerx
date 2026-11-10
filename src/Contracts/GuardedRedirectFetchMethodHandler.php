<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Contracts;

interface GuardedRedirectFetchMethodHandler extends FetchMethodHandler
{
    /** Whether redirects are checked by the handler's transport before they are requested. */
    public function supportsGuardedRedirects(): bool;
}
