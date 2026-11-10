<?php

declare(strict_types=1);

namespace JOOservices\CrawlerX\Fetch\Guard;

final readonly class RobotsTxtRule
{
    public function __construct(
        public bool $allow,
        public string $pattern,
    ) {
    }

    public function matches(string $path): bool
    {
        $pattern = $this->pattern;
        $anchored = str_ends_with($pattern, '$');
        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }

        $regex = str_replace('\\*', '.*', preg_quote($pattern, '~'));

        return preg_match('~^' . $regex . ($anchored ? '$' : '') . '~', $path) === 1;
    }

    public function length(): int
    {
        return strlen($this->pattern);
    }
}
