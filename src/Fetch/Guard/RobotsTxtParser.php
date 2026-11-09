<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

final class RobotsTxtParser
{
    public static function parse(string $content): RobotsTxt
    {
        $groups = [];
        $userAgents = [];
        $rules = [];
        $crawlDelay = null;

        $lines = preg_split('/\R/', $content);
        foreach ($lines === false ? [] : $lines as $line) {
            $line = trim($line);
            $comment = strpos($line, '#');
            if ($comment !== false) {
                $line = rtrim(substr($line, 0, $comment));
            }

            if ($line === '') {
                if ($userAgents !== []) {
                    $groups[] = new RobotsTxtGroup($userAgents, $rules, $crawlDelay);
                    $userAgents = [];
                    $rules = [];
                    $crawlDelay = null;
                }

                continue;
            }

            $separator = strpos($line, ':');
            if ($separator === false) {
                continue;
            }

            $directive = strtolower(trim(substr($line, 0, $separator)));
            $value = trim(substr($line, $separator + 1));

            if ($directive === 'user-agent') {
                if ($rules !== [] || $crawlDelay !== null) {
                    $groups[] = new RobotsTxtGroup($userAgents, $rules, $crawlDelay);
                    $userAgents = [];
                    $rules = [];
                    $crawlDelay = null;
                }

                if ($value !== '') {
                    $userAgents[] = $value;
                }

                continue;
            }

            if ($userAgents === []) {
                continue;
            }

            if ($directive === 'allow' || $directive === 'disallow') {
                if ($value !== '') {
                    $rules[] = new RobotsTxtRule($directive === 'allow', $value);
                }
            } elseif ($directive === 'crawl-delay' && is_numeric($value)) {
                $crawlDelay = max(0.0, (float) $value);
            }
        }

        if ($userAgents !== []) {
            $groups[] = new RobotsTxtGroup($userAgents, $rules, $crawlDelay);
        }

        return new RobotsTxt($groups);
    }
}
