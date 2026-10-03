<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Tools\Canary;

use JOOservices\CrawlerX\Contracts\LoginCookieProvider;

final class EnvLoginCookieProvider implements LoginCookieProvider
{
    /** @param array<string, string> $values */
    public function __construct(private readonly array $values)
    {
    }

    /** @return array<string, string> */
    public function cookiesFor(string $site): array
    {
        $header = Env::cookieForSite($site, $this->values);
        if ($header === null) {
            return [];
        }

        $cookies = [];
        foreach (explode(';', $header) as $part) {
            $separator = strpos($part, '=');
            if ($separator === false) {
                continue;
            }

            $name = trim(substr($part, 0, $separator));
            $value = trim(substr($part, $separator + 1));
            if ($name !== '' && $value !== '') {
                $cookies[$name] = $value;
            }
        }

        return $cookies;
    }
}
